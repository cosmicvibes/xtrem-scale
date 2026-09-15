<?php

namespace CosmicVibes\XtremScale;

use Exception;

class XtremScale
{
    private string $ipAddress;
    private int $sendPort;
    private int $receivePort;
    private $socket;
    private int $timeout;

    /**
     * Create a new XtremScale instance
     *
     * @param string $ipAddress Scale IP address
     * @param int $sendPort Port to send commands to (default: 4445)
     * @param int $receivePort Port to receive data from (default: 5556)
     * @param int $timeout Timeout in seconds (default: 5)
     */
    public function __construct(
        string $ipAddress,
        int $sendPort = 4445,
        int $receivePort = 5556,
        int $timeout = 5
    ) {
        $this->ipAddress = $ipAddress;
        $this->sendPort = $sendPort;
        $this->receivePort = $receivePort;
        $this->timeout = $timeout;
    }

    /**
     * Get the current weight from the scale
     *
     * @return array{weight: string, success: bool, error: string|null}
     */
    public function getWeight(): array
    {
        try {
            // Create UDP socket
            $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($this->socket === false) {
                throw new Exception('Failed to create socket: ' . socket_strerror(socket_last_error()));
            }

            // Set socket options. Receive timeout is short because we resend the
            // request on every attempt (UDP is lossy, so we retransmit every
            // ~200ms rather than
            // sending once and waiting), not because we expect the total wait to
            // be short.
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, [
                'sec' => 0,
                'usec' => 200000
            ]);
            socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);

            // Bind to receive port
            if (!socket_bind($this->socket, '0.0.0.0', $this->receivePort)) {
                throw new Exception('Failed to bind socket: ' . socket_strerror(socket_last_error($this->socket)));
            }

            // Send start streaming command, resending every ~200ms until we get a
            // response, since a single lost UDP packet would otherwise leave us
            // waiting for a reply the scale never received the request for.
            $startCommand = "\x02" . "00FFE10110000" . "\x03" . "\r\n";

            $reading = null;
            $status = null;
            $consecutiveStatusFrames = 0;
            $maxAttempts = (int) ceil($this->timeout / 0.2);
            $attempt = 0;

            // Keep reading until a weight-stream frame arrives. The scale answers the
            // start command with an acknowledgement frame first (parameter address
            // 1011), which carries no weight, so a single datagram is not enough.
            while ($reading === null && $attempt < $maxAttempts) {
                $this->sendCommand($startCommand);

                $response = $this->receiveData();

                if ($response !== null) {
                    $reading = $this->parseWeightFrame($response);

                    if ($reading === null) {
                        // With nothing on the platform the scale streams status frames
                        // (address 0100) instead of weights. Once a few arrive with no
                        // weight among them, it is telling us it has none to give --
                        // report that immediately rather than burning the full timeout.
                        $frameStatus = $this->parseStatusFrame($response);

                        if ($frameStatus !== null) {
                            $status = $frameStatus;

                            if (++$consecutiveStatusFrames >= 5) {
                                break;
                            }
                        }
                    }
                }

                $attempt++;
            }

            // Send stop streaming command
            $stopCommand = "\x02" . "00FFE10100000" . "\x03" . "\r\n";
            $this->sendCommand($stopCommand);

            // Close socket
            socket_close($this->socket);

            if ($reading === null) {
                return $status !== null
                    ? $this->failure($status['message'], $status['code'])
                    : $this->failure('No response from scale');
            }

            return $reading + [
                'success' => true,
                'error' => null,
            ];

        } catch (Exception $e) {
            if (isset($this->socket) && is_resource($this->socket)) {
                socket_close($this->socket);
            }

            return $this->failure($e->getMessage());
        }
    }

    /**
     * An unsuccessful read, shaped like a successful one so callers can read the
     * same keys either way.
     *
     * @return array{weight: string, gross: null, tare: null, net: null, unit: null, stable: bool, net_displayed: bool, status_code: int|null, success: bool, error: string}
     */
    private function failure(string $error, ?int $statusCode = null): array
    {
        return [
            'weight' => '',
            'gross' => null,
            'tare' => null,
            'net' => null,
            'unit' => null,
            'stable' => false,
            'net_displayed' => false,
            'status_code' => $statusCode,
            'success' => false,
            'error' => $error,
        ];
    }

    /**
     * Parse a status frame (parameter address 0100), which the scale streams instead
     * of weights when it has none to report.
     *
     * @return array{code: int, message: string}|null
     */
    private function parseStatusFrame(string $data): ?array
    {
        $start = strpos($data, "\x02");
        $end = strpos($data, "\x03", $start === false ? 0 : $start);

        if ($start === false || $end === false) {
            return null;
        }

        $frame = substr($data, $start + 1, $end - $start - 1);

        if (substr($frame, 5, 4) !== '0100' || ! ctype_xdigit(substr($frame, 11, 2))) {
            return null;
        }

        $code = hexdec(substr($frame, 11, 2)) & 0x1F;

        if ($code === 0) {
            return null;
        }

        return [
            'code' => $code,
            'message' => match ($code) {
                1 => 'Scale error 01: flash memory error',
                2 => 'Scale error 02: ADC failure',
                3 => 'Scale error 03: load cell signal out of range',
                4 => 'Load cell signal too high (ADC H)',
                5 => 'Load cell signal too low (ADC L)',
                7 => 'Overload: weight exceeds the scale maximum',
                8 => 'Scale is not showing a weight (display shows dashes)',
                default => "Scale reported error status {$code}",
            },
        ];
    }

    /**
     * Send a command to the scale
     *
     * @param string $command
     * @return bool
     */
    private function sendCommand(string $command): bool
    {
        $sent = socket_sendto(
            $this->socket,
            $command,
            strlen($command),
            0,
            $this->ipAddress,
            $this->sendPort
        );

        return $sent !== false;
    }

    /**
     * Receive data from the scale
     *
     * @return string|null
     */
    private function receiveData(): ?string
    {
        $buffer = '';
        $from = '';
        $port = 0;

        $bytes = @socket_recvfrom($this->socket, $buffer, 1024, 0, $from, $port);

        if ($bytes === false || $bytes === 0) {
            return null;
        }

        return $buffer;
    }

    /**
     * Parse a weight-stream frame.
     *
     * A frame looks like this, with
     * STX and the trailing ETX + CRLF stripped:
     *
     *     0100r01071AW   0.133kgT   0.000kgS01461
     *     ^^^^ ^^^^^^   ^^^^^^^^^^ ^^^^^^^^^^ ^^^
     *     0-3  5-10     11-21      22-32      33-36
     *
     *     0-1    sender id
     *     2-3    destination id, "00" or "FF"
     *     5-8    parameter address; "0107" is the weight stream
     *     9-10   data length, two hex digits
     *     11     'W' gross-weight marker
     *     12-19  gross weight
     *     20-21  unit
     *     22     'T' tare marker
     *     23-30  tare
     *     33     'S' status marker
     *     34-36  weight status flags, three hex digits
     *     37-38  checksum
     *
     * Anything that is not a well-formed 0107 frame returns null so the caller can
     * wait for the next datagram.
     *
     * @return array{status_code: null, weight: string, gross: float, tare: float, net: float, unit: string, stable: bool, net_displayed: bool}|null
     */
    private function parseWeightFrame(string $data): ?array
    {
        // Take only what is between STX and ETX.
        $start = strpos($data, "\x02");
        $end = strpos($data, "\x03", $start === false ? 0 : $start);

        if ($start === false || $end === false) {
            return null;
        }

        $frame = substr($data, $start + 1, $end - $start - 1);

        // Destination must be this host ("00") or broadcast ("FF").
        if (! in_array(substr($frame, 2, 2), ['00', 'FF'], true)) {
            return null;
        }

        // Parameter address: only the weight stream carries a weight.
        if (substr($frame, 5, 4) !== '0107') {
            return null;
        }

        $length = substr($frame, 9, 2);

        if (! ctype_xdigit($length) || strlen($frame) !== hexdec($length) + 13) {
            return null;
        }

        // Field markers, as a guard against a malformed frame of the right length.
        if (substr($frame, 11, 1) !== 'W' || substr($frame, 22, 1) !== 'T') {
            return null;
        }

        $grossField = substr($frame, 12, 8);
        $tareField = substr($frame, 23, 8);
        $status = substr($frame, 34, 3);

        if (! is_numeric(trim($grossField)) || ! is_numeric(trim($tareField)) || ! ctype_xdigit($status)) {
            return null;
        }

        $gross = (float) trim($grossField);
        $tare = (float) trim($tareField);
        $net = $gross - $tare;
        $unit = trim(substr($frame, 20, 2));

        $flags = hexdec($status);
        $stable = (bool) ($flags & 0x04);
        $netDisplayed = (bool) ($flags & 0x08);

        // Mirror the resolution the scale itself is displaying.
        $trimmedGross = trim($grossField);
        $point = strpos($trimmedGross, '.');
        $decimals = $point === false ? 0 : strlen($trimmedGross) - $point - 1;

        $displayed = $netDisplayed ? $net : $gross;

        return [
            'status_code' => null,
            'weight' => number_format($displayed, $decimals, '.', '') . ' ' . $unit,
            'gross' => $gross,
            'tare' => $tare,
            'net' => $net,
            'unit' => $unit,
            'stable' => $stable,
            'net_displayed' => $netDisplayed,
        ];
    }

    /**
     * Static method for quick weight reading
     *
     * @param string $ipAddress
     * @return array
     */
    public static function read(string $ipAddress): array
    {
        $scale = new self($ipAddress);
        return $scale->getWeight();
    }
}

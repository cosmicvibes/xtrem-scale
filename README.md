# Xtrem Scale Package

Laravel package for connecting to Gram Xtreme F-06 scales over network.

## Installation

Add the package to your Laravel project:

```bash
composer require cosmicvibes/xtrem-scale
```

Or add it as a local package by adding this to your main `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../xtrem-scale"
        }
    ],
    "require": {
        "cosmicvibes/xtrem-scale": "@dev"
    }
}
```

Then run:

```bash
composer update
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=xtrem-scale-config
```

Add your scale IP address to `.env`:

```env
XTREM_SCALE_IP=192.168.1.100
XTREM_SCALE_SEND_PORT=4445
XTREM_SCALE_RECEIVE_PORT=5556
XTREM_SCALE_TIMEOUT=5
```

## Usage

### Using the Facade

```php
use CosmicVibes\XtremScale\XtremScale;

// Get weight from configured scale
$result = app('xtrem-scale')->getWeight();

if ($result['success']) {
    echo "Weight: " . $result['weight'];
} else {
    echo "Error: " . $result['error'];
}
```

### Direct instantiation

```php
use CosmicVibes\XtremScale\XtremScale;

$scale = new XtremScale('192.168.1.100');
$result = $scale->getWeight();
```

### Quick read

```php
use CosmicVibes\XtremScale\XtremScale;

$result = XtremScale::read('192.168.1.100');
```

### API Route Example

```php
Route::get('/scale/weight', function () {
    $result = app('xtrem-scale')->getWeight();

    return response()->json($result);
});
```

## Response Format

The `getWeight()` method returns an array with:

```php
[
    'weight' => '0.133 kg',   // What the scale's own display shows
    'gross' => 0.133,         // Gross weight
    'tare' => 0.0,            // Tare
    'net' => 0.133,           // Gross minus tare
    'unit' => 'kg',
    'stable' => true,         // False while the reading is still settling
    'net_displayed' => false, // True when the scale is displaying net, not gross
    'status_code' => null,    // Scale status code when it cannot report a weight
    'success' => true,
    'error' => null,
]
```

`weight` mirrors whichever of gross/net the scale itself is displaying, at the
scale's own resolution.

When the scale cannot report a weight it streams a status frame instead, and
`success` is `false` with `status_code` set and `error` describing it:

| `status_code` | Meaning |
|---|---|
| 1 | Flash memory error |
| 2 | ADC failure |
| 3 | Load cell signal out of range |
| 4 / 5 | Load cell signal too high / too low |
| 7 | Overload — weight exceeds the scale maximum |
| 8 | Scale is not showing a weight (display shows dashes) |

Status code 8 is normal: it is what the scale reports when it is idle with nothing
on the platform.

## Reading continuously (multiple consumers)

A scale can only usefully be read by **one process at a time**:

- Its UDP receive port can only be usefully bound once per host — two readers on the
  same machine will steal each other's datagrams.
- Its streaming state is **global**, so a client that stops the stream blinds every
  other reader, on every machine.

`getWeight()` is therefore only appropriate for occasional one-off reads. To serve
many consumers, have a single process own the stream and publish the readings for
everything else to consume:

```php
$scale = new XtremScale('192.168.1.100', 4444, 5555);
$scale->openStream();          // binds the socket, starts the stream

while (true) {
    // Multiplex with socket_select() across several scales if needed
    while (($reading = $scale->readStreamFrame()) !== null) {
        $latest = $reading;    // the scale pushes ~14 frames/sec
    }

    $scale->keepStreamAlive(); // periodically re-assert the stream
    // publish $latest somewhere shared (cache, Redis, ...)
}

$scale->closeStream();
```

Neither `getWeight()` nor `closeStream()` sends the stop command, precisely so that
one consumer can never switch off another's stream.

## Requirements

- PHP 8.2 or higher
- Laravel 10.x, 11.x, or 12.x
- UDP sockets enabled (sockets extension)

## Network Configuration

The Gram Xtreme F-06 scale uses UDP communication:
- **Send Port**: 4445 (commands sent to scale)
- **Receive Port**: 5556 (data received from scale)

Ensure your firewall allows UDP traffic on these ports.

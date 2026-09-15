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
            "url": "./packages/xtrem-scale"
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
    'weight' => '123.45 kg',  // The weight reading
    'success' => true,         // Whether the read was successful
    'error' => null            // Error message if success is false
]
```

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- UDP sockets enabled (sockets extension)

## Network Configuration

The Gram Xtreme F-06 scale uses UDP communication:
- **Send Port**: 4445 (commands sent to scale)
- **Receive Port**: 5556 (data received from scale)

Ensure your firewall allows UDP traffic on these ports.

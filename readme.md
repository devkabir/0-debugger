# WordPress Debugger

WordPress Debugger is a tool designed to identify and resolve errors on a WordPress website.

## Installation

To install, download the tool as a zip file and follow the standard WordPress plugin installation process.

## Requirements

This tool is compatible with PHP versions 7.1 to later

## Output

Upon successful execution, you’ll see the results as shown in the included image

![](./result.png)

### Dump any variable data
- dump a variable using the `dump` function
- dump a variable and stop execution using the `dd` function
![](./dump.png)

### Write a log to debug the API response

```php
DevKabir\WPDebugger\write_log( 'Plugin Loaded' ); // Write log in the plugin directory.
DevKabir\WPDebugger\write_log( 'Plugin Loaded', __DIR__ ); // Write log in the directory where the function is called.
```

### Write a log to debug SQL queries in your plugin
```php
DevKabir\WPDebugger\write_query(); // Write log in \wp-content folder.
DevKabir\WPDebugger\write_query( __DIR__ ); // Write log in the directory where the function is called.
```

## Using as a MU-Plugin
If you want to use this plugin as a must-use plugin (MU-Plugin), you can easily achieve that by following these steps:

- Download or copy the code from the following Gist: [MU-Plugin Example Code](https://gist.github.com/devkabir/78ae9d52ce6faa6f639292ebe48eae17).
- Paste the code into the wp-content/mu-plugins directory.
- This code will ensure the plugin is automatically activated and loaded, eliminating the need for manual activation.

Feel free to modify the code according to your needs.

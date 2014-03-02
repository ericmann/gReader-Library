#gReader Library (Original: https://github.com/ericmann/gReader-Library)

PHP class library for providing access to the RSS services compatible with Google Reader API.
Enables authentication and provides public methods for general Google Reader actions.

This project is dual-licensed as GPLv2 and MIT.

# Example of use
```html
<body>
<pre>
<?php
require('greader.class.php');
$gr = new JDMReader('aUserName', 'aPassword', 'https://your.server.example/', 'https://your.server.example/');

echo htmlspecialchars($gr->listAll());
?>
</pre>

<?php echo $gr->listUnread(100); ?>

</body>
```

# Compatible RSS services
* [FreshRSS](http://freshrss.org/) https://github.com/marienfressinaud/FreshRSS
 * `https://your.server.example/s/FreshRSS/p/api/greader.php/`

# Known limitations
* POST changes have not been tested yet

# Barcode Generator for Publications
PHP script specifically for generating ISBN and ISSN barcodes.  
No it can't be used to generate other EAN-13 barcodes, but you could modify it to do so.

## Usage
```php
// ISBN
$barcode = new \sqkhor\Barcode\PublicationBarcode();
$svg = $barcode->render('svg', '978-967-2088-62-2');

// ISBN with Add On
$barcode = new \sqkhor\Barcode\PublicationBarcode();
$svg = $barcode->render('svg', '978-967-2088-62-2', '50999');

// ISSN with Issue Number
$barcode = new \sqkhor\Barcode\PublicationBarcode();
$svg = $barcode->render('svg', '3009-1004', '01');
```
## To-Do
* PNG / JPG render capability
* Class parameters to set bar width & height
* Reset after every use
* Error handling
* Comments / documentations
* Tests

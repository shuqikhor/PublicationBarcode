# Barcode Generator for Publications
PHP script specifically for generating ISBN and ISSN barcodes.  
It could be used to generate other EAN-13 barcodes too, though not designed to do so.  

## Usage
```php
// ISBN
$barcode = new \sqkhor\Barcode\PublicationBarcode();
$svg = $barcode->render('svg', '978-967-2088-62-2');

// ISBN with add on
$barcode = new \sqkhor\Barcode\PublicationBarcode();
$svg = $barcode->render('svg', '978-967-2088-62-2', '50999');

// ISSN with issue number
$barcode = new \sqkhor\Barcode\PublicationBarcode();
$svg = $barcode->render('svg', '3009-1004', '01');
```

## Sample
ISBN:  
<img src="sample-isbn.svg" width="300" style="background: #fff">

ISSN with issue number:  
<img src="sample-issn.svg" width="300" style="background: #fff">

## To-Do
- [ ] PNG / JPG render capability
- [ ] Class parameters to set bar width & height
- [ ] Reset after every use
- [ ] Error handling
- [x] Comments / documentations
- [ ] Tests

# Barcode Generator for Publications
PHP script specifically for generating ISBN and ISSN barcodes.  
It could be used to generate other EAN-13 barcodes too, though not designed to do so.  

## Installation

You could either download everything in `src/` into your project, or install via composer:  
```
composer require sqkhor/publication-barcode
```

## Usage
```php
// ISBN
$barcode = new \SQKhor\Barcode\PublicationBarcode();
$svg = $barcode->render('svg', '978-967-2088-62-2');

// ISBN with add on
$barcode = new \SQKhor\Barcode\PublicationBarcode();
$svg = $barcode->render('svg', '978-967-2088-62-2', '50999');

// ISSN with issue number
$barcode = new \SQKhor\Barcode\PublicationBarcode();
$svg = $barcode->render('svg', '3009-1004', '01');
```

## Method Parameters
`render(format, code, [addon])`

__format__ (_string_)  
Either one of these: svg | png | jpg | jpeg

__code__ (_string_)  
The 13-digit ISBN / ISSN, or 8-digit ISSN code

__addon__ (_?string_)   
Supplimentary barcode data for price (ISBN) and issue number (ISSN)

## Sample
ISBN:  
<img src="sample-isbn.svg" width="300" style="background: #fff">

ISSN with issue number:  
<img src="sample-issn.svg" width="300" style="background: #fff">

## To-Do
- [x] PNG / JPG render capability
- [x] Class parameters to set bar width & height
- [ ] Reset after every use
- [ ] Error handling
- [x] Comments / documentations
- [ ] Tests

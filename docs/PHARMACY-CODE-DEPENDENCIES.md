# Pharmacy barcode dependency record

The application locks `picqer/php-barcode-generator` v3.3.0 in `api/composer.lock` (constraint ^3.2). It renders Code 128 SVG locally. The locked package requires PHP ^8.2, satisfied by this application’s PHP ^8.3 requirement. It adds no Composer runtime dependency; SVG rendering adds no external image service or GD requirement. Source and documentation: https://github.com/picqer/php-barcode-generator. License: LGPL-3.0-or-later; the package LICENSE.md and source remain with Composer distributions. No vendor source is copied or modified in this repository. Preserve the package license when packaging the deployed application.

The GTIN check-digit validator follows the arithmetic documented by GS1: https://www.gs1.org/services/how-calculate-check-digit-manually. Validation is not a GS1 registration lookup or drug catalogue license, and no code mapping or authenticity is inferred.

Independent local QA uses zxing-cpp 3.1.1 from PyPI outside the application repository, plus the bundled Pillow runtime, to decode a rasterized retained SVG. It is not a production dependency or physical hardware acceptance. No barcode/patient content is sent to an external decoder.

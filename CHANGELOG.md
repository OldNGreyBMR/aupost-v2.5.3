CHANGELOG
Australia Post Shipping Module 2.5.3
------------------------------------
Version 2.5.3 14 Feb 2023:
___________________________________
Files changed in 2.5.3
- Updated for ZC version 1.5.8 and backwards compatible with PHP 7.4


Version 2.5-2 04 Feb 2023:
___________________________________
Files changed in 2.5.2
- Updated for ZC version 1.5.8 and PHP 8.2
- Error msg and error handling when Australia Post servers are down
- define more class public vars
- move the logo display to avoid icon loading on file opening and no quote selected
- change defines
- ensure unique var names to avoid clash between aupost and aupostoverseas modules
- data load select all options and make handling fee 2.00 on all, show handling fees
- restructure logic to not fail with Australia Post invalid codes returned
- disable cache for zc158
- remove redundant options no longer available via API (may be available over the counter)


Australia Post Shipping Module 2.5.0
----------------------------------
Version 2.5-0 16 Nov 2022:
__________________________________
Files changed in 2.5.0
- Updated for ZC version 1.5.8 and PHP 8.1
- added options for regular parcels, express parcels
- round up extra cover value
- do not check insurance if below min cover amt
- Version 2.5 runs on Zen Cart 158
- rearrange logic for PHP8.1
- quotes on AUS; hide ins for parcels where value less than min ins value
- standard formatting - removes some divs
- ensure each option has unique identifier

Australia Post Shipping Module 2.4.2
----------------------------------
Version 2.4-2 31 July 2022:
__________________________________
Files changed in 2.4.2
- Updated for ZC version 1.5.7d and PHP 8.0
- defined constants in aupost.php and aupostoverseas.php
- aupost.php will only return postage charges for Australian destinations
- aupostoverseas.php will only return postage charges for overseas destinations
- postage rates with zero charges and duplicate charges are filtered out
- postage rates are sorted lowest to highest
- Australia Post information URL updated
- postage rates return all rates within a category
- maximum parcel weight set to 22kg
- defaulted weight measurement to gms
- Updated Aus Post codes
- Debug mode only shows valid options
- included letters: regular, priority and express
- included parcels: extra cover and signature for regular and satchels
- updated Australia Post icon
- included css file for debugging options

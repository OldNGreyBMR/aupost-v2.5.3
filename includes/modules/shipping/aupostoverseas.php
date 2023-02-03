<?php
/*
 $Id:   overseasaupost.php,v2.5.2 Feb 2023
        V2.5.2 zen cart 158 php8.2
  BMH 2022-01-30   Line 26    define  MODULE_SHIPPING_OVERSEASAUPOST_HIDE_PARCEL
                    line 144   process only international destinations
                    line 488   correct URL for AusPost API
  BMH 2022-04-01  line 196    Undefined array key "products_weight"
                    line 405    changed logic for debug so invalid options are not included in final list
                    separated out 2nd level debug WITH Constant BMHDEBUG_INT1
        2022-07-22  BMHDEBUG_INT1 and BMHDEBUG_INT2
        2022-07-30  formatting
                    reset quotes['id'] as it is required by shipping.php but not used anywhere else
                    Economy Air quoted fro AP has a bug that does not allow extra cover 
                    Added check for min insurance cover 
                    Included Courier ins
        2022-08-16  Version 2.5 runs on Zen Cart 158
                    rearrange logic for PHP8.1
                    allowed for invalid country destination
        2022-09-05  check for missing authkey  
        2023-02-01  define all class variables
                    data load select all options and make handling fee 2.00 on all, show handling fees
                    Error msg and error handling when Australia Post servers are down
                    change var names to avoid clash with Aupost (local post plugin)
                    recoding to bypass error from AustPost API where returned options for INT_PARCEL_AIR_OWN_PACKAGING do not match documentation
                    removed $info - was support for now redundant registered post
*/
// BMHDEBUG switches
define('BMHDEBUG_INT1','No');          // BMH 2nd level debug to display all returned data from Aus Post
define('BMHDEBUG_INT2','No');          // BMH 3rd level debug to display all returned data from Aus Post
define('USE_CACHE_INT','No');           // BMH disable cache // set to 'No' for testing; 
define('MINEXTRACOVER_OVERIDE','Yes');  // BMH obtain cost for extra cover even if $ordervalue < $MINVALUEEXTRACOVER // Used for testing.
////

//BMH declare constants
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_HIDE_PARCEL')) { define('MODULE_SHIPPING_OVERSEASAUPOST_HIDE_PARCEL',''); } // 
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS')) { define('MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS',''); }
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_TYPES1')) { define('MODULE_SHIPPING_OVERSEASAUPOST_TYPES1',''); }
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_STATUS')) { define('MODULE_SHIPPING_OVERSEASAUPOST_STATUS',''); }
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_SORT_ORDER')) { define('MODULE_SHIPPING_OVERSEASAUPOST_SORT_ORDER',''); }
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_ICONS')) { define('MODULE_SHIPPING_OVERSEASAUPOST_ICONS',''); }
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS')) { define('MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS',''); }
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_CORE_WEIGHT')) { define('MODULE_SHIPPING_OVERSEASAUPOST_CORE_WEIGHT',''); } 

// ++++++++++++++++++++++++++
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_AUTHKEY')) { define('MODULE_SHIPPING_OVERSEASAUPOST_AUTHKEY','') ;}
if (!defined('AUPOST_TESTMODE_AUTHKEY')) { define('AUPOST_TESTMODE_AUTHKEY','28744ed5982391881611cca6cf5c240') ;} // DO NOT CHANGE
if (!defined('AUPOST_URL_TEST')) {define('AUPOST_URL_TEST','test.npe.auspost.com.au'); }                                               // No longer used - leave as prod url
if (!defined('AUPOST_URL_PROD')) { define('AUPOST_URL_PROD','digitalapi.auspost.com.au'); }                                            // Aus Post URL
if (!defined('PARCEL_INT_URL_STRING')) { define('PARCEL_INT_URL_STRING','/postage/parcel/international/service.xml?');}                // Aust Post URI api what services are avail for destination 
if (!defined('PARCEL_INT_URL_STRING_CALC')) { define('PARCEL_INT_URL_STRING_CALC','/postage/parcel/international/calculate.xml?'); }   // Aust Post URI api calc charges for each type


// set product variables

$aupost_url_string = AUPOST_URL_PROD ; 

$lettersize = 0; //set flag for letters

  if (BMHDEBUG_INT2 == "Yes") {  // outputs on admin | modules | shipping page
    // echo ' <br>ln63 MODE= ' . AUPOST_MODE . ' //$aupost_url_string = ' .$aupost_url_string . ' aupost_url_apiKey= ' . $aupost_url_apiKey ;
    } 

  if (BMHDEBUG_INT2 == "Yes") { // outputs on admin | modules | shipping page
       //  echo '<br>line67 MODE= ' . AUPOST_MODE . ' aupost_url_apiKey= ' . $aupost_url_apiKey ;
    } 
    
// class constructor

class aupostoverseas extends base
{
    public $add_int;            //
    public $allowed_methods;    //
    public $aus_rate_int_int;   // tax rate
    public $code;               // Declare shipping module alias code
    public $description;        // Shipping module display description
    public $dest_country;       // destination country
    public $dims;               //
    public $enabled;            // Shipping module status    
    public $icon;               // Shipping module icon filename/path
    public $included_option;    //
    public $logo;               // au post logo
    public $myarray = [];        // 
    public $myorder;            // 
    public $ordervalue;         // value of order
    public $qu2;                // quote string
    public $qu2_sig;            // quote2 string for signatures
    public $quotes =[];         // 
    public $sort_order;         // sort order for quotes options
    public $tax_basis;          // 
    public $tax_class;          // 
    public $testmethod;         //
    public $title;              // Shipping module display name
    public $usemod;             //
    public $usetitle;           //
    public $_check;              //
    
    
    function __construct()
    {
        global $order, $db, $template ;
       
        $this->code = 'aupostoverseas';
        $this->title = MODULE_SHIPPING_OVERSEASAUPOST_TEXT_TITLE ;
        $this->description = MODULE_SHIPPING_OVERSEASAUPOST_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_SHIPPING_OVERSEASAUPOST_SORT_ORDER;
        $this->icon = $template->get_template_dir('aupost_logo.jpg', '' ,'','images/icons'). '/aupost_logo.jpg';
        if (zen_not_null($this->icon)) $this->quotes['icon'] = zen_image($this->icon, $this->title);
        $this->logo = $template->get_template_dir('aupost_logo.jpg', '','' ,'images/icons'). '/aupost_logo.jpg';
        $this->tax_class = MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS;
        $this->tax_basis = 'Shipping' ;    // It'll always work this way, regardless of any global settings

        // disable only when entire cart is free shipping
        // placed after variables declared ZC158 PHP8.1
        if (zen_get_shipping_enabled($this->code))  $this->enabled = ((MODULE_SHIPPING_OVERSEASAUPOST_STATUS == 'True') ? true : false);

        if (MODULE_SHIPPING_OVERSEASAUPOST_ICONS != "No" ) {
            if (zen_not_null($this->logo)) $this->title = zen_image($this->logo, $this->title) ;
        }
      // get letter and parcel methods defined
        $this->allowed_methods = explode(", ", MODULE_SHIPPING_OVERSEASAUPOST_TYPES1) ;
    }

    // class methods
    // // functions
    function quote($method = '')
    {
        
        global $db, $order, $cart, $currencies, $template, $parcelweight, $packageitems;

        if (zen_not_null($method) && (isset($_SESSION['overseasaupostQuotes']))) {
            $testmethod = $_SESSION['overseasaupostQuotes']['methods'] ;

            foreach($testmethod as $temp) {
                $search = array_search("$method", $temp) ;
                if (strlen($search) > 0 && $search >= 0) break ;
            }

        $usemod = $this->title ; 
        $usetitle = $temp['title'] ;

        if (MODULE_SHIPPING_OVERSEASAUPOST_ICONS != "No" ) {  // strip the icons //
            if (preg_match('/(title)=("[^"]*")/',$this->title, $module))  $usemod = trim($module[2], "\"") ;
            if (preg_match('/(title)=("[^"]*")/',$temp['title'], $title)) $usetitle = trim($title[2], "\"") ;
        }
            
        //  Initialise our quote array(s)  // quotes['id'] required in includes/classes/shipping.php
            
        $this->quotes = ['id' => $this->code, 'module' => $this->title];
            $methods = [] ;
            $this->quotes = [
                'id' => $this->code,
                'module' => $usemod,
                'methods' => [
                    [
                    'id' => $method,
                    'title' => $usetitle,
                    'cost' =>  $temp['cost']
                    ]
                ]
            ];


            if ($this->tax_class >  0) {
                $this->quotes['tax'] = zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
            }

            return $this->quotes;   // return a single quote
        }  ////////////////////////////  Single Quote Exit Point //////////////////////////////////

      // Maximums
        $MAXWEIGHT_P = 20 ;     // BMH  20kgs for International
        $MAXLENGTH_P = 105 ;    // 105cm max parcel length
        $MAXGIRTH_P =  140 ;    // 140cm max parcel girth  ( (width + height) * 2)
        $MINVALUEEXTRACOVER = 101;  // Aust Post amount for min insurance charge
        
        $OPTIONCODE_SIG = 'INT_SIGNATURE_ON_DELIVERY';  // set codes for extra options
        $OPTIONCODE_COVER = 'INT_EXTRA_COVER';          // set codes for extra options
        
        // default dimensions //
        $x = explode(',', MODULE_SHIPPING_OVERSEASAUPOST_DIMS) ;
        $defaultdims = array($x[0],$x[1],$x[2]) ;
        sort($defaultdims) ;  // length[2]. width[1], height=[0]

        // initialise variables
        $parcelwidth = 0 ;
        $parcellength = 0 ;
        $parcelheight = 0 ;
        $parcelweight = 0 ;
        $cube = 0 ;
        $details = ' ';

        $frompcode = MODULE_SHIPPING_OVERSEASAUPOST_SPCODE;
        $dest_country=($order->delivery['country']['iso_code_2'] ?? '');  //BMH 
        
        //BMH  move country check here        
        if (empty($dest_country)) {
            //BMH There are no quotes 
         return;} //  This will occur with guest user first quote where no postcode is available //BMH DEBUG 
        
        if ($dest_country == "AU") {
            //BMH There are no quotes 
         return;} // BMH exit if AU as AU is a separate module
        
        $MSGNOTRACKING =  "<b> (No tracking) </b>";         // label append // emphasis to minimise complaints
        $MSGSIGINC =  " (Sig inc)";         // label append
        
        $topcode = str_replace(" ","",($order->delivery['postcode']));
        $aus_rate_int = (float)$currencies->get_value('AUD') ;
        $ordervalue=$order->info['total'] / $aus_rate_int ;
        $tare = MODULE_SHIPPING_OVERSEASAUPOST_TARE ;
            // EOF PARCELS - values
        
        if ($aus_rate_int == 0) {                                   // included by BMH to avoid possible divide  by zero error 
            $aus_rate_int = (float)$currencies->get_value(AUS);     // if AUD zero/undefined then try AUS
            if ($aus_rate_int == 0) {
                $aus_rate_int = 1;                                  // if still zero initialise to 1.00 to avoid divide by zero error
            }
        }           // BMH 
            
        $ordervalue=$order->info['total'] / $aus_rate_int ;                 // total cost for insurance
        // BMH Only proceed for AU addresses
        if ($dest_country == "AU") {
            return $this->quotes ;     // BMH exit if AU
        } 
     
        $FlatText = " Using AusPost Flat Rate." ;
            
        // loop through cart extracting productIDs and qtys //
        $myorder = $_SESSION['cart']->get_products();
     
        for($x = 0 ; $x < count($myorder) ; $x++ )
        {
            //$producttitle = $myorder[$x]['id'] ; 
            $t = $myorder[$x]['id'] ;  // BMH better name
            $q = $myorder[$x]['quantity'];
            $w = $myorder[$x]['weight'];
     
            $dim_query = "select products_length, products_height, products_width from " . TABLE_PRODUCTS . " where products_id='$t' limit 1 ";
            $dims = $db->Execute($dim_query);

            // re-orientate //
            $var = array($dims->fields['products_width'], $dims->fields['products_height'], $dims->fields['products_length']) ; sort($var) ;
            $dims->fields['products_length'] = $var[2] ; $dims->fields['products_width'] = $var[1] ;  $dims->fields['products_height'] = $var[0] ;
            
            // if no dimensions provided use the defaults
            if($dims->fields['products_height'] == 0) {$dims->fields['products_height'] = $defaultdims[0] ; }
            if($dims->fields['products_width']  == 0) {$dims->fields['products_width']  = $defaultdims[1] ; }
            if($dims->fields['products_length'] == 0) {$dims->fields['products_length'] = $defaultdims[2] ; }
            if($w == 0) {$w = 1 ; }  // 1 gram minimum
            
            $parcelweight += $w * $q;
            
            // get the cube of these items
            $itemcube =  ($dims->fields['products_width'] * $dims->fields['products_height'] * $dims->fields['products_length'] * $q) ;
            // Increase widths and length of parcel as needed
            if ($dims->fields['products_width'] >  $parcelwidth)  { $parcelwidth  = $dims->fields['products_width']  ; }
            if ($dims->fields['products_length'] > $parcellength) { $parcellength = $dims->fields['products_length'] ; }
            // Stack on top on existing items
            $parcelheight =  ($dims->fields['products_height'] * ($q)) + $parcelheight  ;
            
            $packageitems =  $packageitems + $q ;

            // Useful debugging information //
            
            if ( MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" ) {
                $dim_query = "select products_name from " . TABLE_PRODUCTS_DESCRIPTION . " where products_id='$t' limit 1 ";
                $name = $db->Execute($dim_query); // BMH Undefined array key "products_weight"

                echo "<center><table class=\"aupost-debug-table\" border=1 ><th colspan=8>Debugging information ln235 [aupost Flag set in Admin console | shipping | aupostoverseas]</hr>
                <tr><th>Item " . ($x + 1) . "</th><td colspan=7>" . $name->fields['products_name'] . "</td>
                <tr><th width=1%>Attribute</th><th colspan=3>Item</th><th colspan=4>Parcel</th></tr>
                <tr><th>Qty</th><td>&nbsp; " . $q . "<th>Weight</th><td>&nbsp; " . ($dims->fields['products_weight'] ?? '') . "</td>
                <th>Qty</th><td>&nbsp;$packageitems</td><th>Weight</th><td>&nbsp;" ; echo $parcelweight + (($parcelweight* $tare)/100) ; echo "</td></tr>
                <tr><th>Dimensions</th><td colspan=3>&nbsp; " . $dims->fields['products_length'] . " x " . $dims->fields['products_width'] . " x "  . $dims->fields['products_height'] . "</td>
                <td colspan=4>&nbsp;$parcellength  x  $parcelwidth  x $parcelheight </td></tr>
                <tr><th>Cube</th><td colspan=3>&nbsp; " . $itemcube . "</td><td colspan=4>&nbsp;" . ($parcelheight * $parcellength * $parcelwidth) . " </td></tr>
                <tr><th>CubicWeight</th><td colspan=3>&nbsp;" . (($dims->fields['products_length'] * $dims->fields['products_height'] * $dims->fields['products_width']) * 0.00001 * 250) . "Kgs  </td><td colspan=4>&nbsp;" . (($parcelheight * $parcellength * $parcelwidth) * 0.00001 * 250) . "Kgs </td></tr>
                </table></center> " ;
            }   // eof debug display table
        }   // end for loop

        //////////// // PACKAGE ADJUSTMENT FOR OPTIMAL PACKING // ////////////
        // package created, now re-orientate and check dimensions
        $parcelheight = ceil($parcelheight);  // round up to next integer // cm for accuracy in pricing 
        $var = array($parcelheight, $parcellength, $parcelwidth) ; sort($var) ;
        $parcelheight = $var[0] ; $parcelwidth = $var[1] ; $parcellength = $var[2] ;
        $girth = ($parcelheight * 2) + ($parcelwidth * 2)  ;

        $parcelweight = $parcelweight + (($parcelweight*$tare)/100) ;

        if (MODULE_SHIPPING_OVERSEASAUPOST_WEIGHT_FORMAT == "gms") {$parcelweight = $parcelweight/1000 ; }

        //  save dimensions for display purposes on quote form 
        $_SESSION['swidth'] = $parcelwidth ; $_SESSION['sheight'] = $parcelheight ;
        $_SESSION['slength'] = $parcellength ;                                      // $_SESSION['boxes'] = $shipping_num_boxes ;

        // Check for maximum length allowed
        if($parcellength > $MAXLENGTH_P) {
            $cost = $this->_get_int_error_cost($dest_country) ;

           if ($cost == 0) return  ;    // no quote
       
            $methods[] = array('title' => ' (AusPost excess length)', 'cost' => $cost ) ; // update method
            $this->quotes['methods'] = $methods;   // set it
            return $this->quotes;
        }  // exceeds AustPost maximum length. No point in continuing.

       // Check girth
        if($girth > $MAXGIRTH_P ) {
            $cost = $this->_get_int_error_cost($dest_country) ;
           if ($cost == 0)  return  ;   // no quote
           $methods[] = array('title' => ' (AusPost excess girth)', 'cost' => $cost ) ;
           $this->quotes['methods'] = $methods;   // set it
           return $this->quotes;
        }  // exceeds AustPost maximum girth. No point in continuing.

        if ($parcelweight > $MAXWEIGHT_P) {
            $cost = $this->_get_int_error_cost($dest_country) ;
            if ($cost == 0)  return  ;   // no quote
            $methods[] = array('title' => ' (AusPost excess weight)', 'cost' => $cost ) ;
            $this->quotes['methods'] = $methods;   // set it
            return $this->quotes;
        }  // exceeds AustPost maximum weight. No point in continuing.

        // Check to see if cache is useful
        if (USE_CACHE_INT == "Yes") {   //BMH DEBUG disable cache for testing
            if(isset($_SESSION['overseasaupostParcel'])) {
                $test = explode(",", $_SESSION['overseasaupostParcel']) ;
        
                if (
                    ($test[0] == $dest_country) &&
                    ($test[1] == $topcode) &&
                    ($test[2] == $parcelwidth) &&
                    ($test[3] == $parcelheight) &&
                    ($test[4] == $parcellength) &&
                    ($test[5] == $parcelweight) &&
                    ($test[6] == $ordervalue)
                    )
                {
                if ( MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" ) {
                    echo "<center><table class=\"aupost-debug\"border=1 ><td align=center><font color=\"#FF0000\">Using Cached quotes </font></td></table></center>" ;
                }
        
                $this->quotes =  $_SESSION['overseasaupostQuotes'] ;
                return $this->quotes ;  
                ///////////////////////////////////  Cache Exit Point //////////////////////////////////
                } // No cache match -  get new quote from server //
        
            }  // No cache session -  get new quote from server //
        } // end cache option //BMH DEBUG 
        ///////////////////////////////////////////////////////////////////////////////////////////////

        // always save new session  CSV //
        $_SESSION['overseasaupostParcel'] = implode(",", array($dest_country, $topcode, $parcelwidth, $parcelheight, $parcellength, $parcelweight, $ordervalue)) ;
        $shipping_weight = $parcelweight ;  // global value for zencart
        
        // Set destination code ( postcode if AU, else 2 char iso country code )
        $dcode = ($dest_country == "AU") ? $topcode:$dest_country ;

        $flags = ((MODULE_SHIPPING_OVERSEASAUPOST_HIDE_PARCEL == "No") || ( MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" )) ? 0:1 ;
        
        $aupost_url_string = AUPOST_URL_PROD ;  // Server query string //
        
        /////  Initialise our quotes['id'] required in includes/classes/shipping.php
       $this->quotes = array('id' => $this->code, 'module' => $this->title); // BMH ** DEBUG
        
        if (BMHDEBUG_INT2 == "Yes") { 
            echo '<p class="aupost-debug"> <br>parcels ***<br>aupost ln361 url called ' .'https://' . $aupost_url_string . PARCEL_INT_URL_STRING . "&country_code=$dcode&weight=$parcelweight" . '</p>'; 
        }
        //// ++++++++++++++++++++++++++++++
        // get parcel api';
        $qu = $this->get_auspost_api('https://' . $aupost_url_string . PARCEL_INT_URL_STRING . "&country_code=$dcode&weight=$parcelweight") ;       
        
     
        if ((strpos($qu,"<") != 1) && (str_contains($qu,"error"))) { 
            echo '<br> ERROR IN POSTAGE CONFIGURATION. PLEASE CONTACT THE STORE ADMINISTRATOR'; 
            exit; 
            } // BMH check for error msg eg Auth key is incorrect
        
        if ((MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" ) && (BMHDEBUG_INT1 == "Yes")) { 
            echo "<table class=\"aupost-debug\"><tr><td>Server Returned: aupostint ln374 <br>" ; //BMH DEBUG . $qu . "</td></tr></table> <br>" ; 
        }

        $xml = ($qu == '') ? array() : new SimpleXMLElement($qu)  ; // If we have any results, parse them into an array  
        
        if ((MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" ) && (BMHDEBUG_INT1 == "Yes") && (BMHDEBUG_INT2 == "Yes")) {
            echo "<p class=\"aupost-debug\" ><strong>>> Server Returned BMHDEBUG_INT1+2 line 380 XML output
                << <br> </strong><textarea rows=50 cols=100 style=\"margin:0;\"> ";
            print_r($xml) ; // exit ; // ORIG DEBUG to output api xml // BMH DEBUG
            echo "</textarea></p>";
        }
        
        // Check for nil returm from Australia Post
        if (empty($xml->service)) {
            if (BMHDEBUG_INT2 == "Yes") {
                echo '<p class="aupost-debug" ln389 quote returned is blank - Country not allowed </p>' ;
            }
            $methods[] = array( 'id' => "Error",  'title' => "Invalid destination Country - Not allowed to send parcel here.", 'cost' => 9999.99 ) ; // display reason
            // echo '<br> ln392 No Quote <br>';
            $this->quotes['methods'] = $methods;   // set it
            return $this->quotes;
        }

            //// !!!!!!!!!!!! ////
            if (MINEXTRACOVER_OVERIDE == "Yes") {
                if ($ordervalue < $MINVALUEEXTRACOVER){
                    $ordervalue = $MINVALUEEXTRACOVER;
                } 
            }   //BMH DEBUG NOTE: mask for testing to force extra cover on ammount below the min threshold// comment out for production
            //// !!!!!!!!!!!! ////
        
        ///////////////////////////////////////
        //  loop through the quotes retrieved //

        $i = 0 ;  // counter
                    
        if (BMHDEBUG_INT2 == "Yes") {
            echo "<p class=\"aupost-debug\" </p> ln403 dump allowed methods  <br>"; var_dump($this->allowed_methods); // BMH ** DEBUG
            echo '<br>';  
        }   // BMH ** DEBUG
        
        foreach($xml as $foo => $bar)
        {
            //BMH keep API code for label
            $code = ($xml->service[$i]->code); $code = str_replace("_", " ", $code); $code = substr($code,11); //strip first 11 chars;     //BMH keep API code for label
            
            $id = str_replace("_", "", $xml->service[$i]->code); // remove underscores from AusPost methods. 
                                                                 // Zen Cart uses underscore as delimiter between module and method. 
                                                                 // underscores must also be removed from case statements below.
            $cost = (float)($xml->service[$i]->price);
            
            $description =  "PARCEL " . (ucwords(strtolower($code))) ; // BMH prepend PARCEL to code in sentence case

            if ((MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" ) && (BMHDEBUG_INT1 == "Yes")) { 
                echo "<table class=\"aupost-debug\"><tr><td>" ; 
                echo "ln426 ID= $id DESC= $description COST= $cost ex" ; 
                echo "</td></tr></table>" ; 
            } // BMH 2nd level debug each line of quote parsed

            $add_int = 0 ; $f = 0 ; 
     
            switch ($id) {
                
                case  "INTPARCELAIROWNPACKAGING" ;                          //BMH NOTE No tracking MAX Weight 3.5kg limited countries
                    $description = $description . ' ' . $MSGNOTRACKING;     // ADD NOTE NOTRACKING FOR ECONOMY AIR  
                    $included_option =0; 
                    $add_int = MODULE_SHIPPING_OVERSEASAUPOST_AIRMAIL_HANDLING ;
                    if (in_array("Economy Air Mail", $this->allowed_methods)) { 
                        $allowed_option = "Economy Air Mail";
                        $included_option =1;
                    
                        $add_int = MODULE_SHIPPING_OVERSEASAUPOST_AIRMAIL_HANDLING ; $f = 1 ;
                        $code_sig = 0;
                        $code_cover = 0;
                        $details= $this->_handling($details,$currencies,$add_int,$aus_rate_int);  // check if handling rates included
                        
                        $methods[] = array('id' => "$id",  'title' => $description . " [" . $allowed_option . "] " . $details, 'cost' => $cost);   // update method //BMH ADDIN
                    }
                    if ( in_array("Economy Air Mail Insured +sig", $this->allowed_methods) ) {

                        $included_option =1;
                        $code_sig = 1;
                        $code_cover = 1;

                       if ($ordervalue < $MINVALUEEXTRACOVER) { $code_cover = 0; break; }
                        $OPTIONCODE_SIG = 'INT_SIGNATURE_ON_DELIVERY';
                        $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                        $OPTIONCODE_COVER = 'INT_AIR_EXTRA_COVER'; //BMH DEBUG INVALID API ERROR // BMH
                        
                        $id_option = "INTPARCELAIROWNPACKAGING" . "INTAIREXTRACOVER";
                        $allowed_option = "Economy Air Mail Insured +sig";
                        $option_offset = 0;
                     
                       $result_int_secondary_options = $this-> _get_int_secondary_options( $add_int, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER, $dcode, $parcelweight, $optionservicecode, $OPTIONCODE_COVER, $OPTIONCODE_SIG, $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate_int,$code_sig, $code_cover);

                        if ((strlen($id) >1) && ($included_option <> 0)) {
                            $methods[] = $result_int_secondary_options ;
                        }
                    }

                    if ( in_array("Economy Air Mail +sig", $this->allowed_methods) ) {

                        $included_option =1;                       
                            $code_sig = 1;
                            $code_cover = 0;
                                                        
                            $OPTIONCODE_SIG = 'INT_SIGNATURE_ON_DELIVERY';
                            $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                            $OPTIONCODE_COVER = ' ';
                            $id_option = "INTPARCELAIROWNPACKAGING" . "INTSIGNATUREONDELIVERY";
                            $allowed_option = "Economy Air Mail +sig";

                            $option_offset = 0;

                          $result_int_secondary_options = $this-> _get_int_secondary_options( $add_int, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER, $dcode, $parcelweight, $optionservicecode, $OPTIONCODE_COVER, $OPTIONCODE_SIG, $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate_int,$code_sig, $code_cover);
                               
                            if (strlen($id) >1){
                                $methods[] = $result_int_secondary_options ;
                            }
                    }
                

                    if ( in_array("Economy Air Mail Insured (no sig)", $this->allowed_methods) ) {

                        $included_option =1;
                                                 
                            $code_sig = 0;
                            $code_cover = 1;
                            
                            if ($ordervalue < $MINVALUEEXTRACOVER) { $code_cover = 0; break; }
                            
                            $OPTIONCODE_SIG = ' ';
                            $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                            $OPTIONCODE_COVER = 'INTAIR_EXTRA_COVER';
                            
                            $id_option = "INTPARCELAIROWNPACKAGING" . "INTAIREXTRACOVER";
                            $allowed_option = "Economy Air Mail Insured (no sig)";
                            $option_offset1 = 0;
                           
                           $result_int_secondary_options = $this-> _get_int_secondary_options( $add_int, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER, $dcode, $parcelweight, $optionservicecode, $OPTIONCODE_COVER, $OPTIONCODE_SIG, $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate_int,$code_sig, $code_cover);
                            
                            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMHDEBUG2 == "Yes")) { 
                                echo '<p class="aupost-debug"> ln515 $result_int_secondary_options = ' ; //BMH ** DEBUG
                                var_dump($result_int_secondary_options);
                                echo ' <\p>';
                            }
                            if (strlen($id) >1){
                                $methods[] = $result_int_secondary_options ;
                            }
                    }

                    if ($included_option = 0) {
                        $id = '';
                    }

                break;
            
                case  "INTPARCELSEAOWNPACKAGING" ;                          //BMH NOTE MIN Weight 2kg limited countries
                    $description = $description . ' ' . $MSGNOTRACKING;     // ADD NOTE NO TRACKING FOR SEA 
                    $included_option =0; 
                    if (in_array("Sea Mail", $this->allowed_methods))
                    {
                        $included_option =1; 
                        $allowed_option = "Sea Mail";
                        $add_int =  MODULE_SHIPPING_OVERSEASAUPOST_SEAMAIL_HANDLING ; $f = 1 ; 
                        $code_sig = 0;
                        $code_cover = 0;
                        $details= $this->_handling($details,$currencies,$add_int,$aus_rate_int);  // check if handling rates included
                        
                        $methods[] = array('id' => "$id",  'title' => $description . " [" . $allowed_option . "] " . $details, 'cost' => $cost); // update method
                    }
                    
                    if ( in_array("Sea Mail Insured +sig", $this->allowed_methods) ) {
                        $included_option =1;     
                        $code_sig = 1;
                        $code_cover = 1; 
                        if ($ordervalue < $MINVALUEEXTRACOVER) { $code_cover = 0; break; }
                        
                        $OPTIONCODE_SIG = 'INT_SIGNATURE_ON_DELIVERY';
                        $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                        $OPTIONCODE_COVER = 'INT_EXTRA_COVER';
                        $id_option = "INTPARCELSEAOWNPACKAGING" . "INTSIGNATUREONDELIVERY";
                        $allowed_option = "Sea Mail Insured +sig";
                        $option_offset = 0;
                        
                      $result_int_secondary_options = $this-> _get_int_secondary_options( $add_int, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER, $dcode, $parcelweight, $optionservicecode, $OPTIONCODE_COVER, $OPTIONCODE_SIG, $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate_int,$code_sig, $code_cover);
                       
                        if (strlen($id) >1) {
                            $methods[] = $result_int_secondary_options ;
                        }
                    }
                    
                    if ( in_array("Sea Mail +sig", $this->allowed_methods) ) {
                        $included_option =1;    
                            $code_sig = 1;
                            $code_cover = 0; 
                            $OPTIONCODE_SIG = 'INT_SIGNATURE_ON_DELIVERY';
                            $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                            $OPTIONCODE_COVER = 'INT_EXTRA_COVER';
                            $id_option = "INTPARCELSEAOWNPACKAGING" . "INTSIGNATUREONDELIVERY";
                            $allowed_option = "Sea Mail +sig";
                            $option_offset = 0;
                            
                          $result_int_secondary_options = $this-> _get_int_secondary_options( $add_int, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER, $dcode, $parcelweight, $optionservicecode, $OPTIONCODE_COVER, $OPTIONCODE_SIG, $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate_int,$code_sig, $code_cover);
                            
                            if (strlen($id) >1){
                                $methods[] = $result_int_secondary_options ;
                            }
                    }
                    

                    if ( in_array("Sea Mail Insured (no sig)", $this->allowed_methods) ) {
                         $included_option =1; 
                        $code_sig = 0;
                        $code_cover = 1;
                        $OPTIONCODE_COVER = 'INT_EXTRA_COVER';
                        $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                        $OPTIONCODE_SIG = '';
                        $allowed_option = "Sea Mail Insured (no sig)";
                        $option_offset1 = 0;
                    
                        $result_int_secondary_options = $this-> _get_int_secondary_options( $add_int, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER, $dcode, $parcelweight, $optionservicecode, $OPTIONCODE_COVER, $OPTIONCODE_SIG, $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate_int,$code_sig, $code_cover);
                    
                        if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMHDEBUG2 == "Yes")) { 
                            echo '<p class="aupost-debug"> ln610 $result_int_secondary_options = ' ; 
                            var_dump($result_int_secondary_options);
                            echo ' <\p>';
                        }   //BMH ** DEBUG
                        
                        if (strlen($id) >1){
                            $methods[] = $result_int_secondary_options ;
                        }

                    }
                break;
            
                case  "INTPARCELSTDOWNPACKAGING" ;
                    $included_option =0; 
                    if ( in_array("Standard Post International", $this->allowed_methods)) {
                        $included_option =1; 
                         $allowed_option = "Standard Post International";
                          $add_int = MODULE_SHIPPING_OVERSEASAUPOST_STANDARD_HANDLING ; $f = 1 ;
                          $code_sig = 0;
                          $code_cover = 0;
                          $details= $this->_handling($details,$currencies,$add_int,$aus_rate_int);  // check if handling rates included

                            $methods[] = array('id' => "$id",  'title' => $description . " [" . $allowed_option . "] " . //BMH ADDIN
                            $details, 'cost' => $cost);   // update method
                    }
                    if ( in_array("Standard Post International Insured +sig", $this->allowed_methods) ) {
                         $included_option =1; 
                          $code_sig = 1;
                          $code_cover = 1;
                          
                          if ($ordervalue < $MINVALUEEXTRACOVER) { 
                              $code_cover = 0; ; 
                          }
                          else {
                              $OPTIONCODE_SIG = 'INT_SIGNATURE_ON_DELIVERY';
                              $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                              $OPTIONCODE_COVER = 'INT_EXTRA_COVER';
                              $id_option = "INTPARCELSTDOWNPACKAGING" . "INTSIGNATUREONDELIVERY";
                              $allowed_option = "Standard Post International Insured +sig";
                              $option_offset = 0;
                          
                              $result_int_secondary_options = $this-> _get_int_secondary_options( $add_int, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER, $dcode, $parcelweight, $optionservicecode, $OPTIONCODE_COVER, $OPTIONCODE_SIG, $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate_int,$code_sig, $code_cover);
                         
                              if (strlen($id) >1) {
                                  $methods[] = $result_int_secondary_options ;
                              }
                          }
                    }
                    
                    if ( in_array("Standard Post International +sig", $this->allowed_methods) ) {
                         $included_option =1; 
                        $code_sig = 1;
                          $code_cover = 0;
                          $OPTIONCODE_SIG = 'INT_SIGNATURE_ON_DELIVERY';
                          $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                          $OPTIONCODE_COVER = 'INT_EXTRA_COVER';
                          $id_option = "INTPARCELSTDOWNPACKAGING" . "INTSIGNATUREONDELIVERY";
                          $allowed_option = "Standard Post International +sig";

                          $option_offset = 0;
                          
                        $result_int_secondary_options = $this-> _get_int_secondary_options( $add_int, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER, $dcode, $parcelweight, $optionservicecode, $OPTIONCODE_COVER, $OPTIONCODE_SIG, $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate_int,$code_sig, $code_cover);
                          
                          if (strlen($id) >1){
                              $methods[] = $result_int_secondary_options ;
                          }
                    }

                    if ( in_array("Standard Post International Insured (no sig)", $this->allowed_methods) ) {
                        $included_option =1; 
                          $code_sig = 0;
                          $code_cover = 1;
                          if ($ordervalue < $MINVALUEEXTRACOVER) { 
                              $code_cover = 0; 
                          }
                          else {
                              $OPTIONCODE_COVER = 'INT_EXTRA_COVER';
                              $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                              $OPTIONCODE_SIG = '';
                              $id_option = "INTPARCELSTDOWNPACKAGING" . "INTEXTRACOVER";
                              $allowed_option = "Standard Post International Insured (no sig)";
                              $option_offset1 = 0;
                         
                              $result_int_secondary_options = $this-> _get_int_secondary_options( $add_int, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER, $dcode, $parcelweight, $optionservicecode, $OPTIONCODE_COVER, $OPTIONCODE_SIG, $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate_int,$code_sig, $code_cover);
                          
                              if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMHDEBUG2 == "Yes")) { 
                                  echo '<p class="aupost-debug"> ln653 $result_int_secondary_options = ' ; //BMH ** DEBUG
                                  var_dump($result_int_secondary_options);
                                  echo ' <\p>';
                              }
                              if (strlen($id) >1){
                                  $methods[] = $result_int_secondary_options ;
                              }
                          }
                    }
                break;

                case  "INTPARCELEXPOWNPACKAGING" ;
                    $included_option =0; 
                    if (in_array("Express Post International", $this->allowed_methods)) {
                        $included_option =1; 
                        $allowed_option = "Express Post International";
                        $description = $description . ' ' . $MSGSIGINC ;    // sig included
                        $add_int = MODULE_SHIPPING_OVERSEASAUPOST_STANDARD_HANDLING ; $f = 1 ;
                        $code_sig = 0;
                        $code_cover = 0;
                        $details= $this->_handling($details,$currencies,$add_int,$aus_rate_int);  // check if handling rates included
                        
                            $methods[] = array('id' => "$id",  'title' => $description . " [" . $allowed_option . "] " . //BMH ADDIN
                            $details, 'cost' => $cost);   // update method
                    }
                    
                    if ( in_array("Express Post International (sig inc) + Insured", $this->allowed_methods) ) {
                        $included_option =1; 
                        $description = $description . ' ';// DO NOT append $MSGSIGINC // sig already included in description
                        $code_sig = 0;
                        $code_cover = 1;

                        $OPTIONCODE_SIG = 'INT_SIGNATURE_ON_DELIVERY';
                        $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                        $OPTIONCODE_COVER = 'INT_EXTRA_COVER';
                        $id_option = "INTPARCELEXPDOWNPACKAGING" . "INTEXTRACOVER";
                        $allowed_option = "Express Post International (sig inc) + Insured";
                        $option_offset = 0;
                    
                        $result_int_secondary_options = $this-> _get_int_secondary_options( $add_int, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER, $dcode, $parcelweight, $optionservicecode, $OPTIONCODE_COVER, $OPTIONCODE_SIG, $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate_int,$code_sig, $code_cover);
                        
                        if (strlen($id) >1) {
                        $methods[] = $result_int_secondary_options ;
                        }
                    }
                break;
            
                case  "INTPARCELCOROWNPACKAGING" ;
                    $included_option =0; 
                    if (in_array("Courier International", $this->allowed_methods)) {
                        $included_option =1; 
                        $allowed_option = "Courier International";
                        $add_int = MODULE_SHIPPING_OVERSEASAUPOST_COURIER_HANDLING ; $f = 1 ;
                        
                        $details= $this->_handling($details,$currencies,$add_int,$aus_rate_int);  // check if handling rates included
                        
                            $methods[] = array('id' => "$id",  'title' => $description . " [" . $allowed_option . "] " . 
                            $details, 'cost' => $cost);   // update method
                    } 
                    if ( in_array("Courier International Insured", $this->allowed_methods) ) {
                        $included_option =1; 
                        $code_sig = 0;
                        $code_cover = 1;
                        
                    
                        $OPTIONCODE_SIG = '';
                        $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                        $OPTIONCODE_COVER = 'INT_EXTRA_COVER';
                        $id_option = "INTPARCELCOROWNPACKAGING" . "INTEXTRACOVER";
                        $allowed_option = "Courier International Insured";
                        $option_offset = 0;
                        
                        $result_int_secondary_options = $this-> _get_int_secondary_options( $add_int, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER, $dcode, $parcelweight, $optionservicecode, $OPTIONCODE_COVER, $OPTIONCODE_SIG, $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate_int,$code_sig, $code_cover);
                       
                        if (strlen($id) >1) {
                            $methods[] = $result_int_secondary_options ;
                        }
                    }
                break;
                
                case  "INTPARCELREGULARPACKAGELARGE";      // garbage collector
                    $cost = 0;$f=0; $add_int= 0;
                    // echo "shouldn't be here"; //BMH debug do nothing - ignore the code
                break;
                
            }   // eof switch


            //// bof only list valid options  //// 
            if ((($cost > 0) && ($f == 1)) ) {      //
                $cost = $cost + $add_int ;          // add handling fee
                if ( MODULE_SHIPPING_OVERSEASAUPOST_CORE_WEIGHT == "Yes")  {
                    $cost = ($cost * $shipping_num_boxes) ; 
                }
                // // ++++++++
            }   // eof list option for normal operation
            
            $cost = $cost / $aus_rate_int;      // cost includes postage
            
            if (( MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" ) && (BMHDEBUG_INT1 == "Yes") && (BMHDEBUG_INT2 == "Yes"))  { 
                //   echo '<p class="aupost-debug"> ln826 $i=' .$i . "</p>";
            } // BMH ** DEBUG
            
            //// end parcel options that do not have sub options //// 
            
            $i++; // increment the counter to match XML array index
        }  // end foreach loop

        //// ////
        //  check to ensure we have at least one valid quote - produce error message if not.
        if (count($methods) == 0) {                                 // no valid methods
            $cost = $this->_get_int_error_cost($dest_country) ;     // give default cost
           if ($cost == 0)  return  ;                               //

           $methods[] = array( 'id' => "Error",  'title' => MODULE_SHIPPING_OVERSEASAUPOST_TEXT_ERROR ,'cost' => $cost ) ; // display reason
        }

        ////  sort array by cost       ////
        $sarray[] = array() ;
        $resultarr = array() ;
       
        foreach($methods as $key => $value) {
            $sarray[ $key ] = $value['cost'] ;
        }
        asort( $sarray ) ;
        //  remove zero values from postage options
        foreach ($sarray as $key => $value) { 
            if ($value == 0 ) { 
            }
            else 
            {
            $resultarr[ $key ] = $methods [ $key ] ;
            }
        } // BMH eof remove zero values

            $resultarrunique = array_unique($resultarr,SORT_REGULAR);   // remove duplicates
            
            $this->quotes['methods'] = array_values($resultarrunique) ;   // set it

        if ($this->tax_class >  0) {
            $this->quotes['tax'] = zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
        }
        if (BMHDEBUG_INT2 == "Yes") { 
            echo '<p class="aupost-debug"> <br>parcels ***<br>aupost ln872 ' .'https://' . $aupost_url_string . PARCEL_INT_URL_STRING . "&country_code=$dcode&weight=$parcelweight" . '</p>';  
        } //BMH ** DEBUG
     
        $_SESSION['overseasaupostQuotes'] = $this->quotes  ; // save as session to avoid reprocessing when single method required

        return $this->quotes;   //  all done //

       ////  Final Exit Point ////
    }  //// eof function quote method

function _get_int_secondary_options( $add_int, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER, $dcode, $parcelweight, $optionservicecode, $OPTIONCODE_COVER, $OPTIONCODE_SIG, $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate_int,$code_sig, $code_cover)
    { 
        $aupost_url_string = AUPOST_URL_PROD ;  // Server query string //
        $optioncode = 'BLANK';                  // initialise optioncode every time
        $xmlquote_2 = [] ;
        $cost_option = 0;
        //$add_int = 0;
        
        if ((in_array($allowed_option, $this->allowed_methods))) {
            $f = 1 ;
 
            $ordervalue = ceil($ordervalue);  // round up to next integer
            
            if (($code_sig == 1 )&& ($code_cover == 0)) {
                $optioncode = $OPTIONCODE_SIG ; 
                
                if ((MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" ) && (BMHDEBUG_INT1 == "Yes") && (BMHDEBUG_INT2 == "Yes")) {
                echo '<p class="aupost-debug"><br> ln877 sig only ' . PARCEL_INT_URL_STRING_CALC . "&country_code=$dcode&weight=$parcelweight&service_code=$optionservicecode&option_code=$optioncode
                    " . "</p>"; 
                }  // BMH ** DEBUG
                
                $qu2 = $this->get_auspost_api( 'https://' . $aupost_url_string . PARCEL_INT_URL_STRING_CALC. "&country_code=$dcode&weight=$parcelweight&service_code=$optionservicecode&option_code=$optioncode&extra_cover=$ordervalue") ;
        
                $xmlquote_2 = ($qu2 == '') ? array() : new SimpleXMLElement($qu2); // XML format
                
                $cost_option = $xmlquote_2->total_cost;
            }
            //// No sig + extra cover ////
            if (($code_sig == 0) && ($code_cover == 1 )) {
                $optioncode = $OPTIONCODE_COVER ;
                
                if ((MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" ) && (BMHDEBUG_INT1 == "Yes") && (BMHDEBUG_INT2 == "Yes")) {
                echo '<p class="aupost-debug"><br> ln892 ins no sig before get_auspost_api' . PARCEL_INT_URL_STRING_CALC . "&country_code=$dcode&weight=$parcelweight&service_code=$optionservicecode&option_code=$optioncode&extra_cover=$ordervalue" . "</p>"; 
                } // BMH ** DEBUG
    
                $qu2 = $this->get_auspost_api( 'https://' . $aupost_url_string . PARCEL_INT_URL_STRING_CALC. "&country_code=$dcode&weight=$parcelweight&service_code=$optionservicecode&option_code=$optioncode&extra_cover=$ordervalue") ;

                $xmlquote_2 = ($qu2 == '') ? array() : new SimpleXMLElement($qu2); // XML format
                
                if ( isset($xmlquote_2->errorMessage)) {  // BMH ** DEBUG
                    $invalid_option = $xmlquote_2->errorMessage;
                      // pass back a zero value as not a valid option from Australia Post eg extra cover may require a signature as well
                    $cost = 0;
                    $result_int_secondary_options = array("id"=> '',  "title"=>'', "cost"=>$cost) ;  // invalid result
                   return $result_int_secondary_options;
                }
                
                $cost_option = $xmlquote_2->total_cost;
                
            }
            //// signature + extra cover ////
            if (($code_sig == 1) && ($code_cover == 1 )) {
                $optioncode = $OPTIONCODE_SIG ;
                
                if ((MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" ) && (BMHDEBUG_INT1 == "Yes") && (BMHDEBUG_INT2 == "Yes")) {
                    echo '<p class="aupost-debug"><br> ln915 ins + sig get_auspost_api <br>' . 'https://' . $aupost_url_string . PARCEL_INT_URL_STRING_CALC . "&country_code=$dcode&weight=$parcelweight&service_code=$optionservicecode&option_code=$optioncode&extra_cover=$ordervalue" . "</p>"; // BMH ** DEBUG
                }
                // get quote for sig + extra
                $qu2_sig = $this->get_auspost_api( 'https://' . $aupost_url_string . PARCEL_INT_URL_STRING_CALC. "&country_code=$dcode&weight=$parcelweight&service_code=$optionservicecode&option_code=$optioncode&extra_cover=$ordervalue") ;
                
                $qu2 = $qu2_sig;
                
                $xmlquote_2s = ($qu2_sig == '') ? array() : new SimpleXMLElement($qu2_sig); // XML format
                
                if ( isset($xmlquote_2s->errorMessage)) {  // BMH ** DEBUG
                    
                    $invalid_option = $xmlquote_2s->errorMessage;
                      // pass back a zero value as not a valid option from Australia Post eg extra cover may require a signature as well
                    $cost = 0;
                    $result_int_secondary_options = array("id"=> '',  "title"=>'', "cost"=>$cost) ;  // invalid result
                    return $result_int_secondary_options;
                }
                 
                $cost_sig = $xmlquote_2s->total_cost;   // cost inc sig
                $cost_option = $cost_sig;

                $optioncode = $OPTIONCODE_COVER ; // cover quote price varies with cover value

                $qu2_cover = $this->get_auspost_api( 'https://' . $aupost_url_string . PARCEL_INT_URL_STRING_CALC. "&country_code=$dcode&weight=$parcelweight&service_code=$optionservicecode&option_code=$optioncode&extra_cover=$ordervalue") ;
                
                $xmlquote_2c = ($qu2_cover == '') ? array() : new SimpleXMLElement($qu2_cover); // XML format

                if ((MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" ) && (BMHDEBUG_INT1 == "Yes") && (BMHDEBUG_INT2 == "Yes")) {
                    echo "<p class=\"aupost-debug\"><strong>>> Server Returned BMHDEBUG1+2 ln931 secondary options sig + cover xmlquote_2c << </strong> <br> <textarea> ";
                    print_r($xmlquote_2c) ; // exit ; // // BMH DEBUG
                    echo "</textarea> </p>" ; 
                }

                if ( isset($xmlquote_2c->errorMessage)) {  // BMH ** DEBUG
                                        
                    $invalid_option = $xmlquote_2c->errorMessage;
                      // pass back a zero value as not a valid option from Australia Post eg extra cover may require a signature as well
                    $cost = 0;
                    $result_int_secondary_options = array("id"=> '',  "title"=>'', "cost"=>$cost) ;  // invalid result
                    return $result_int_secondary_options;
                }
                
                $cost_cover = $xmlquote_2c->costs->cost[1]->cost ;   // cost for cover
                $cost_option = $cost_option + $cost_cover;
                               
                if ((MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" ) && (BMHDEBUG_INT1 == "Yes") && (BMHDEBUG_INT2 == "Yes")) {
                    echo "<p class=\"aupost-debug\"><strong>>> Server Returned BMHDEBUG1+2 ln949 secondary options sig + cover xmlquote_2c << </strong> <br> <textarea> ";
                    print_r($xmlquote_2c) ; // exit ; // // BMH DEBUG
                    echo "</textarea> </p>" ; 
                }

                // build the main quote
                $xmlquote_2 = ($qu2 == '') ? array() : new SimpleXMLElement($qu2); // XML format
            
                if ( isset($xmlquote_2->errorMessage)) {  // BMH ** DEBUG
                     $invalid_option = $xmlquote_2->errorMessage;
                          // pass back a zero value as not a valid option from Australia Post eg extra cover may require a signature as well
                    $cost = 0;
                    $result_int_secondary_options = array("id"=> '',  "title"=>'', "cost"=>$cost) ;  // invalid result
                    return $result_int_secondary_options;
                }
             } // eof sig + cover
            
            //   valid_option)) 
              
            $desc_option = $allowed_option;
            
            // got all of the option values //
            $cost = $cost_option;
            
            if ((($cost > 0) && ($f == 1))) { // 
                $cost = $cost + $add_int ;
                if ( MODULE_SHIPPING_OVERSEASAUPOST_CORE_WEIGHT == "Yes")  $cost = ($cost * $shipping_num_boxes) ; 
                ////  ++++
                $details= $this->_handling($details,$currencies,$add_int,$aus_rate_int);  // check if handling rates included

            }   // eof list option for normal operation
            
            $cost = $cost / $aus_rate_int;
            $desc_option = "[" . $desc_option . "]";         // delimit option in square brackets
            $result_int_secondary_options = array("id"=>$id_option,  "title"=>$description . ' ' . $desc_option . ' ' .$details, "cost"=>$cost) ;
            // valid result
        }   // eof // options
        
    return $result_int_secondary_options;
    } // eof function _get_int_secondary_options //
//// // BMH _get_int_secondary_options
    
    
    function _get_int_error_cost($dest_country) 
        {
            $x = explode(',', MODULE_SHIPPING_OVERSEASAUPOST_COST_ON_ERROR) ;
            unset($_SESSION['overseasaupostParcel']) ;  // don't cache errors.
            $cost = $dest_country != "AU" ?  $x[0]:$x[1] ;
            if ($cost == 0) {
                $this->enabled = FALSE ;
                unset($_SESSION['overseasaupostQuotes']) ;
            }
            else 
            {  
            $this->quotes = array('id' => $this->code, 'module' => 'Flat Rate'); 
            }
            return $cost;
        }

        //  //  ////////////////////////////////////////////////////////////
    // BMH - parts for admin module 
    ////////////////////////////////////////////////////////////////
    function check()
        {
            global $db;
            if (!isset($this->_check))
            {
                $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_OVERSEASAUPOST_STATUS'");
                $this->_check = $check_query->RecordCount();
            }
            return $this->_check;
        }

    //auspost API
    function get_auspost_api($url)
    {
        if (BMHDEBUG_INT2 == "Yes") {
             echo "<p class=\"aupost-debug\"> ln1031 get_auspost_api \$url= <br>" . $url;
        }
        $crl = curl_init();
        $timeout = 5;
        curl_setopt ($crl, CURLOPT_HTTPHEADER, array('AUTH-KEY:' . MODULE_SHIPPING_OVERSEASAUPOST_AUTHKEY));
        curl_setopt ($crl, CURLOPT_URL, $url);
        curl_setopt ($crl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
        $ret = curl_exec($crl);
        
        // Check the response: if the body is empty then an error occurred
        if (BMHDEBUG_INT2 == "Yes") {
            echo '<p class="aupost-debug"> ln1043 exit get_auspost_api $ret = ' . $ret . '</p> '; // BMH var_dump($ret);

            if (str_contains($ret,"error")) {
                echo '<br>ln1046 Error Occurred $ret= ' . $ret;
            }
        }
        
        //BMH 2023-01-23 added code for when Australia Post is down //BMH bof
        $edata = curl_exec($crl);           //  echo '<br> ln1123 $edata= ' . $edata; //BMH DEBUG
        $errtext = curl_error($crl);        //echo '<br> ln1124 $errtext= ' . $errtext; //BMH DEBUG
        $errnum = curl_errno($crl);         //echo '<br> ln1125 $errnum= ' . $errnum; //BMH DEBUG
        $commInfo = curl_getinfo($crl);     //echo '<br> ln1126 $commInfo= ' . $commInfo; //BMH DEBUG
        if ($edata === "Access denied") {
            $errtext = "<strong>" . $edata . ".</strong> Please report this error to <strong>support@bmh.com.au  ";
        }
        //BMH eof 
        // Check the response; if the body is empty then an error occurred //Code 3 is bad url - check spacing of parameters
        if(!$ret){
            die('<br>Error (curl): "' . curl_error($crl) . '" - Code: ' . curl_errno($crl) .
                ' <br>Major Fault - Cannot contact Australia Post . 
                Please report this error to System Owner. Then try the back button on you browser.');
        }
        
        curl_close($crl);
        return $ret;
    }
    // end auspost API

    function _handling($details,$currencies,$add_int,$aus_rate_int)
    {
        if  (MODULE_SHIPPING_OVERSEASAUPOST_HIDE_HANDLING !='Yes') {
            $details = ' (Inc ' . $currencies->format($add_int / $aus_rate_int ). ' P &amp; H';  // Abbreviated for space saving in final quote format
        }
        return $details;
    }
    
    ////////////////////////////////////////////////////////////////////////////
/// bof install and setup section
function install()
{
    global $db;

      $result = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'SHIPPING_ORIGIN_ZIP'"  ) ;
      $pcode = $result->fields['configuration_value'] ;
      
	if (!$pcode) $pcode = "2000" ;  

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable this module?', 'MODULE_SHIPPING_OVERSEASAUPOST_STATUS', 'True', 'Enable this Module', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Auspost API Key:', 'MODULE_SHIPPING_OVERSEASAUPOST_AUTHKEY', '', 'To use this module, you must obtain a 36 digit API Key from the <a href=\"https:\\developers.auspost.com.au\" target=\"_blank\">Auspost Development Centre</a>', '6', '2', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Dispatch Postcode', 'MODULE_SHIPPING_OVERSEASAUPOST_SPCODE', $pcode, 'Dispatch Postcode?', '6', '3', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value,  configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Shipping Methods for Overseas', 'MODULE_SHIPPING_OVERSEASAUPOST_TYPES1', 'Economy Air Mail +sig, Economy Air Mail Insured +sig, Economy Air Mail Insured (no sig), Sea Mail +sig, Sea Mail Insured +sig, Sea Mail Insured (no sig), Standard Post International, Standard Post International +sig, Standard Post International Insured +sig, Standard Post International Insured (no sig), Express Post International, Express Post International (sig inc) + Insured, Courier International, Courier International Insured', 'Select the methods you wish to allow', '6', '3', 'zen_cfg_select_multioption(array( \'Economy Air Mail\',\'Economy Air Mail +sig\',\'Economy Air Mail Insured +sig\',\'Economy Air Mail Insured (no sig)\', \'Sea Mail\',\'Sea Mail +sig\',\'Sea Mail Insured +sig\',\'Sea Mail Insured (no sig)\', \'Standard Post International\',\'Standard Post International +sig\',\'Standard Post International Insured +sig\',\'Standard Post International Insured (no sig)\', \'Express Post International\',\'Express Post International (sig inc) + Insured\', \'Courier International\',\'Courier International Insured\'), ', now())" ) ;

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee - Economy Air Mail', 'MODULE_SHIPPING_OVERSEASAUPOST_AIRMAIL_HANDLING', '2.00', 'Handling Fee for Economy Air Mail.', '6', '6', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee - Sea Mail', 'MODULE_SHIPPING_OVERSEASAUPOST_SEAMAIL_HANDLING', '2.00', 'Handling Fee for Sea Mail.', '6', '7', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee - Standard Post International', 'MODULE_SHIPPING_OVERSEASAUPOST_STANDARD_HANDLING', '2.00', 'Handling Fee for Standard Post International.', '6', '8', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee - Express Post International', 'MODULE_SHIPPING_OVERSEASAUPOST_EXPRESS_HANDLING', '2.00', 'Handling Fee for Express Post International.', '6', '9', now())");
	$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee - Courier International', 'MODULE_SHIPPING_OVERSEASAUPOST_COURIER_HANDLING', '2.00', 'Handling Fee for Courier International.', '6', '10', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Hide Handling Fees?', 'MODULE_SHIPPING_OVERSEASAUPOST_HIDE_HANDLING', 'No', 'The handling fees are still in the total shipping cost but the Handling Fee is not itemised on the invoice.', '6', '16', 'zen_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Default Parcel Dimensions', 'MODULE_SHIPPING_OVERSEASAUPOST_DIMS', '10,10,2', 'Default Parcel dimensions (in cm). Three comma separated values (eg 10,10,2 = 10cm x 10cm x 2cm). These are used if the dimensions of individual products are not set', '6', '40', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Cost on Error', 'MODULE_SHIPPING_OVERSEASAUPOST_COST_ON_ERROR', '99', 'If an error occurs this Flat Rate fee will be used.</br> A value of zero will disable this module on error.', '6', '20', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Parcel Weight format', 'MODULE_SHIPPING_OVERSEASAUPOST_WEIGHT_FORMAT', 'gms', 'Are your store items weighted by grams or Kilos? (required so that we can pass the correct weight to the server).', '6', '25', 'zen_cfg_select_option(array(\'gms\', \'kgs\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Show AusPost logo?', 'MODULE_SHIPPING_OVERSEASAUPOST_ICONS', 'Yes', 'Show Auspost logo in place of text?', '6', '19', 'zen_cfg_select_option(array(\'No\', \'Yes\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable Debug?', 'MODULE_SHIPPING_OVERSEASAUPOST_DEBUG', 'No', 'See how parcels are created from individual items.</br>Shows all methods returned by the server, including possible errors. <strong>Do not enable in a production environment</strong>', '6', '40', 'zen_cfg_select_option(array(\'No\', \'Yes\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Tare percent.', 'MODULE_SHIPPING_OVERSEASAUPOST_TARE', '10', 'Add this percentage of the items total weight as the tare weight. (This module ignores the global settings that seems to confuse many users. 10% seems to work pretty well.).', '6', '50', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_SHIPPING_OVERSEASAUPOST_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '55', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tax Class', 'MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS', '0', 'Set Tax class or -none- if not registered for GST.', '6', '60', 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now())");

    /////////////////////////  update tables //////

    $inst = 1 ;
    $sql = "show fields from " . TABLE_PRODUCTS;
    $result = $db->Execute($sql);
    while (!$result->EOF) {
      if  ($result->fields['Field'] == 'products_length') {
 	  unset($inst) ;
          break;
      }
      $result->MoveNext();
    }

    if(isset($inst))
    {
      //  echo "new" ;
        $db->Execute("ALTER TABLE " .TABLE_PRODUCTS. " ADD `products_length` FLOAT(6,2) NULL AFTER `products_weight`, ADD `products_height` FLOAT(6,2) NULL AFTER `products_length`, ADD `products_width` FLOAT(6,2) NULL AFTER `products_height`" ) ;
    }
    else
    {
      //  echo "update" ;
        $db->Execute("ALTER TABLE " .TABLE_PRODUCTS. " CHANGE `products_length` `products_length` FLOAT(6,2), CHANGE `products_height` `products_height` FLOAT(6,2), CHANGE `products_width`  `products_width`  FLOAT(6,2)" ) ;
    }
}
    // // BMH removal of module in admin
    function remove()
    {   
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE_SHIPPING_OVERSEASAUPOST_%' ");
    }
 
    //  //  // BMH order of options loaded into admin-shipping
    function keys()
    {
        return array
        (
            'MODULE_SHIPPING_OVERSEASAUPOST_STATUS',
            'MODULE_SHIPPING_OVERSEASAUPOST_AUTHKEY',
            'MODULE_SHIPPING_OVERSEASAUPOST_SPCODE',
            'MODULE_SHIPPING_OVERSEASAUPOST_TYPES1',
            'MODULE_SHIPPING_OVERSEASAUPOST_AIRMAIL_HANDLING',
            'MODULE_SHIPPING_OVERSEASAUPOST_SEAMAIL_HANDLING',
            'MODULE_SHIPPING_OVERSEASAUPOST_STANDARD_HANDLING',
            'MODULE_SHIPPING_OVERSEASAUPOST_EXPRESS_HANDLING',
            'MODULE_SHIPPING_OVERSEASAUPOST_COURIER_HANDLING',
            'MODULE_SHIPPING_OVERSEASAUPOST_COST_ON_ERROR',
            'MODULE_SHIPPING_OVERSEASAUPOST_HIDE_HANDLING',
            'MODULE_SHIPPING_OVERSEASAUPOST_DIMS',
            'MODULE_SHIPPING_OVERSEASAUPOST_WEIGHT_FORMAT',
            'MODULE_SHIPPING_OVERSEASAUPOST_ICONS',
            'MODULE_SHIPPING_OVERSEASAUPOST_DEBUG',
            'MODULE_SHIPPING_OVERSEASAUPOST_TARE',
            'MODULE_SHIPPING_OVERSEASAUPOST_SORT_ORDER',
            'MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS'
        );
    }
/// eof install and setup section
    
}  // end class


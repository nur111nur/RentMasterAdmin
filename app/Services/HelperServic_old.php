<?php
namespace App\Services;

use Symfony\Component\Intl\Currencies;

class HelperService {
    public static function currencyCode(){
        $currencies = Currencies::getNames();
        $currenciesArray = array();
        foreach ($currencies as $key => $value) {
            $currenciesArray[] = array(
                'currency_code' => $key,
                'currency_name' => $value
            );
        }
        return $currenciesArray;
    }


}

?>

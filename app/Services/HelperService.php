<?php
namespace App\Services;

use Exception;
use Illuminate\Support\Str;
use App\Models\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    // Generate Token
    public static function generateToken(){
        return bin2hex(random_bytes(50)); // Generates a secure random token
    }

    // Store Token
    public static function storeToken($email,$token){
        $expiresAt = now()->addMinutes(60); // Set token to expire after 60 minutes
        PasswordReset::updateOrCreate(
            array(
                'email' => $email
            ),
            array(
                'token' => $token,
                'expires_at' => $expiresAt,
            )
        );
        return true;
    }

    // Verify Token
    public static function verifyToken($token){
        $record = PasswordReset::where('token', $token)->where('expires_at', '>', now())->first();
        if ($record) {
            return $record->email;
        } else {
            return false;
        }
    }

    // Make Token Expire
    public static function expireToken($email){
        $expiresAt = now(); // Set token to expire after 60 minutes
        PasswordReset::updateOrCreate(
            array(
                'email' => $email
            ),
            array(
                'expires_at' => $expiresAt,
            )
        );
        return true;
    }

    public static function getEmailTemplatesTypes($type = null){
        // Return required data if type is passed
        if($type){
            switch ($type) {
                case 'verify_mail':
                    return array(
                        'title' => 'Verify Email Account',
                        'type' => 'verify_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'otp','is_condition' => false,
                            ],
                        )
                    );
                case 'reset_password':
                    return array(
                        'title' => 'Password Reset Mail',
                        'type' => 'password_reset_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'link','is_condition' => false,
                            ],
                        )
                    );
                case 'welcome_mail':
                    return array(
                        'title' => 'Welcome Mail',
                        'type' => 'welcome_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                        )
                    );
                case 'property_status':
                    return array(
                        'title' => 'Property status change by admin',
                        'type' => 'property_status_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'property_name','is_condition' => false,
                            ],
                            [
                                'name' => 'status','is_condition' => false,
                            ],
                            [
                                'name' => 'reject_reason','is_condition' => false,
                            ],
                        )
                    );
                case 'project_status':
                    return array(
                        'title' => 'Project status change by admin',
                        'type' => 'project_status_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'project_name','is_condition' => false,
                            ],
                            [
                                'name' => 'status','is_condition' => false,
                            ],
                        )
                    );
                case 'property_ads_status':
                    return array(
                        'title' => 'Property Advertisement status change by admin',
                        'type' => 'property_ads_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'property_name','is_condition' => false,
                            ],
                            [
                                'name' => 'advertisement_status','is_condition' => false,
                            ],
                        )
                    );
                case 'user_status':
                    return array(
                        'title' => 'User account active de-active status',
                        'type' => 'user_status_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'status','is_condition' => false,
                            ],
                        )
                    );
                case 'agent_verification_status':
                    return array(
                        'title' => 'Agent Verification Status',
                        'type' => 'agent_verification_status_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'status','is_condition' => false,
                            ],
                        )
                    );
            }
        }

        // Return All if no type is passed
        return array(
            [
                'title' => 'Verify Account Email Account',
                'type' => 'verify_mail',
            ],
            [
                'title' => 'Password Reset Mail',
                'type' => 'reset_password',
            ],
            [
                'title' => 'Welcome Mail',
                'type' => 'welcome_mail',
            ],
            [
                'title' => 'Property status change by admin',
                'type' => 'property_status',
            ],
            [
                'title' => 'Project status change by admin',
                'type' => 'project_status',
            ],
            [
                'title' => 'Property Advertisement status change by admin',
                'type' => 'property_ads_status',
            ],
            [
                'title' => 'User account active de-active status',
                'type' => 'user_status',
            ],
            [
                'title' => 'Agent Verification Status',
                'type' => 'agent_verification_status',
            ],
        );
    }


    public static function replaceEmailVariables($templateContent, $variables){
        foreach ($variables as $key => $variable) {

            // Create the placeholder format
            $placeholder = '{' . $key . '}';
            $endPlaceHolderPair = "{end_$key}";
            if (strpos($templateContent, $placeholder) !== false && strpos($templateContent, $endPlaceHolderPair) !== false) {
                $pattern=$placeholder.$endPlaceHolderPair;
                $templateContent = str_replace($pattern, $variable, $templateContent);
            }else{
                // Replace the placeholder with the variable format
                $templateContent = str_replace($placeholder, $variable, $templateContent);
            }
        }
        return $templateContent;
    }

    public static function sendMail($data, $requiredEmailException = false){
        try {
            $adminMail = env('MAIL_FROM_ADDRESS');
            Mail::send('mail-templates.mail-template', $data, function ($message) use ($data, $adminMail) {
                $message->to($data['email'])->subject($data['title']);
                $message->from($adminMail, 'Admin');
            });
        } catch (Exception $e) {
            if($requiredEmailException == true){
                DB::rollback();
                throw $e;
            }

            if (Str::contains($e->getMessage(), [
                'Failed',
                'Mail',
                'Mailer',
                'MailManager'
                ])) {
                    Log::error("Cannot send mail, there is issue with mail configuration.");
            } else {
                $logMessage = "Send Mail for property feature status changed";
                Log::error($logMessage . ' ' . $e->getMessage() . '---> ' . $e->getFile() . ' At Line : ' . $e->getLine());
            }
        }
    }


}

?>
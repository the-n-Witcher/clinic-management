<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SmsService
{
    private ParameterBagInterface $params;
    private LoggerInterface $logger;

    public function __construct(
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->params = $params;
        $this->logger = $logger;
    }

    public function sendSms(string $phone, string $message): bool
    {
        if (!$this->params->get('feature_sms_notifications', false)) {
            $this->logger->debug('SMS notifications are disabled');
            return false;
        }

        try {
            $provider = $this->params->get('sms_provider', 'mock');
            
            switch ($provider) {
                case 'twilio':
                    return $this->sendViaTwilio($phone, $message);
                case 'smsru':
                    return $this->sendViaSmsRu($phone, $message);
                case 'mock':
                default:
                    $this->logger->info('Mock SMS sent', [
                        'phone' => $phone,
                        'message' => $message
                    ]);
                    return true;
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to send SMS', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    private function sendViaTwilio(string $phone, string $message): bool
    {
        $accountSid = $this->params->get('twilio_account_sid');
        $authToken = $this->params->get('twilio_auth_token');
        $fromNumber = $this->params->get('twilio_from_number');
        
        if (!$accountSid || !$authToken || !$fromNumber) {
            $this->logger->error('Twilio credentials not configured');
            return false;
        }
        
        // $client = new Client($accountSid, $authToken);
        // $client->messages->create($phone, [
        //     'from' => $fromNumber,
        //     'body' => $message
        // ]);
        
        $this->logger->info('Twilio SMS would be sent', [
            'phone' => $phone,
            'message' => $message
        ]);
        
        return true;
    }

    private function sendViaSmsRu(string $phone, string $message): bool
    {
        $apiId = $this->params->get('smsru_api_id');
        
        if (!$apiId) {
            $this->logger->error('SMS.ru API ID not configured');
            return false;
        }
        
        // $ch = curl_init("https://sms.ru/sms/send");
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        //     'api_id' => $apiId,
        //     'to' => $phone,
        //     'msg' => $message,
        //     'json' => 1
        // ]));
        
        $this->logger->info('SMS.ru SMS would be sent', [
            'phone' => $phone,
            'message' => $message
        ]);
        
        return true;
    }

    public function validatePhone(string $phone): bool
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (preg_match('/^(\+7|8)[0-9]{10}$/', $phone)) {
            return true;
        }
        
        if (preg_match('/^\+[1-9][0-9]{1,14}$/', $phone)) {
            return true;
        }
        
        return false;
    }

    public function formatPhoneForSms(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (str_starts_with($phone, '8')) {
            $phone = '+7' . substr($phone, 1);
        }
        
        if (str_starts_with($phone, '7')) {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }

    public function getSmsBalance(): ?float
    {
        $provider = $this->params->get('sms_provider', 'mock');
        
        try {
            switch ($provider) {
                case 'twilio':
                    return 100.0;
                case 'smsru':
                    return 50.0;
                default:
                    return null;
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to get SMS balance', [
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
}
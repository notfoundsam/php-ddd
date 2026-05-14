<?php

use Aws\Ses\SesClient;
use Aws\Ses\Exception\SesException;
use Email\Email_Driver;

/**
 * Class Email_Driver_Ses
 *
 * AWS SES driver. Use `set_configuration_set('main')` to attach an SES
 * configuration set to the message (resolved by name lookup at send time).
 */
class Email_Driver_Ses extends Email_Driver
{
    private ?string $messageId = null;

    private ?string $configurationSetName = null;

    private ?string $lastErrorMessage = null;

    public function set_configuration_set(?string $name): void
    {
        $this->configurationSetName = $name !== null && $name !== '' ? $name : null;
    }

    /**
     * @return  bool    Success boolean.
     */
    protected function _send(): bool
    {
        $client = new SesClient([
            'version' => 'latest',
            'region'  => 'ap-northeast-1',
        ]);

        try {
            $data = $this->build_message();
            $message = $data['header'] . $data['body'];

            $args = ['RawMessage' => ['Data' => $message]];
            if ($this->configurationSetName !== null) {
                $args['ConfigurationSetName'] = $this->configurationSetName;
            }

            $result = $client->sendRawEmail($args);
            $this->messageId = $result->get('MessageId');
            $this->lastErrorMessage = null;
        } catch (SesException $e) {
            $this->lastErrorMessage = $e->getAwsErrorMessage() ?: $e->getMessage();
            Log::error('Sending message failed.', [
                'class' => __CLASS__,
                'method' => __FUNCTION__,
                'exception' => $this->lastErrorMessage,
            ]);
            return false;
        }

        return true;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getLastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }
}

<?php

namespace Xigen\ContactAttachment\Mail\Template;

/**
 * TransportBuilder class
 */
class TransportBuilder extends \Magento\Framework\Mail\Template\TransportBuilder
{
    /**
     * @var \Xigen\ContactAttachment\Mail\Message
     */
    protected $message;
    
    /**
     * Add an attachment to the message.
     *
     * @param string $content
     * @param string $fileName
     * @param string $fileType
     * @return $this
     */
    public function addAttachment($content, $fileName, $fileType)
    {
        $this->message->setBodyAttachment($content, $fileName, $fileType);
        return $this;
    }
    
    /**
     * After all parts are set, add them to message body.
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function prepareMessage()
    {
        parent::prepareMessage();
        $this->message->setPartsToBody();
        return $this;
    }

    /**
     * Set mail from address
     * @param string|array $from
     * @return $this
     */
    public function setFrom($from)
    {
        $result = $this->_senderResolver->resolve($from);
        $this->message->setFrom($result['email'], $result['name']);
        return $this;
    }
}

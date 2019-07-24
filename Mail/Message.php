<?php

namespace Xigen\ContactAttachment\Mail;

use Zend\Mime\Part;
use Zend\Mime\Mime;
use Zend\Mime\PartFactory;
use Zend\Mail\MessageFactory as MailMessageFactory;
use Zend\Mime\MessageFactory as MimeMessageFactory;

/**
 * Message class
 */
class Message extends \Magento\Framework\Mail\Message implements \Magento\Framework\Mail\MailMessageInterface
{
    /**
     * @var \Zend\Mime\PartFactory
     */
    protected $partFactory;

    /**
     * @var \Zend\Mime\MessageFactory
     */
    protected $mimeMessageFactory;

    /**
     * @var \Zend\Mail\Message
     */
    private $zendMessage;

    /**
     * @var \Zend\Mime\Part[]
     */
    protected $parts = [];

    /**
     * Message constructor.
     *
     * @param \Zend\Mime\PartFactory $partFactory
     * @param \Zend\Mime\MessageFactory $mimeMessageFactory
     * @param string $charset
     */
    public function __construct(
        PartFactory $partFactory,
        MimeMessageFactory $mimeMessageFactory,
        $charset = 'utf-8'
    ) {
        $this->partFactory = $partFactory;
        $this->mimeMessageFactory = $mimeMessageFactory;
        $this->zendMessage = MailMessageFactory::getInstance();
        $this->zendMessage->setEncoding($charset);
        parent::__construct($charset);
    }

    /**
     * Add the HTML mime part to the message.
     * @param string $content
     * @return $this
     */
    public function setBodyText($content)
    {
        $textPart = $this->partFactory->create();

        $textPart->setContent($content)
            ->setType(Mime::TYPE_TEXT)
            ->setEncoding(Mime::ENCODING_QUOTEDPRINTABLE)
            ->setCharset($this->zendMessage->getEncoding());

        $this->parts[] = $textPart;
        return $this->setBody($content);
    }

    /**
     * Add the text mime part to the message.
     * @param string $content
     * @return $this
     */
    public function setBodyHtml($content)
    {
        $htmlPart = $this->partFactory->create();

        $htmlPart->setContent($content)
            ->setType(Mime::TYPE_HTML)
            ->setEncoding(Mime::ENCODING_QUOTEDPRINTABLE)
            ->setCharset($this->zendMessage->getEncoding());

        $this->parts[] = $htmlPart;
        return $this->setBody($content);
    }

    /**
     * Add the attachment mime part to the message.
     *
     * @param string $content
     * @param string $fileName
     * @param string $fileType
     * @return $this
     */
    public function setBodyAttachment($content, $fileName, $fileType)
    {
        $attachmentPart = $this->partFactory->create();

        $attachmentPart->setContent($content)
            ->setType($fileType)
            ->setFileName($fileName)
            ->setDisposition(Mime::DISPOSITION_ATTACHMENT)
            ->setEncoding(Mime::ENCODING_BASE64);

        $this->parts[] = $attachmentPart;

        return $this;
    }

    /**
     * Set parts to Zend message body.
     *
     * @return $this
     */
    public function setPartsToBody()
    {
        $mimeMessage = $this->mimeMessageFactory->create();
        $mimeMessage->setParts($this->parts);
        $this->zendMessage->setBody($mimeMessage);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated
     * @see \Magento\Framework\Mail\Message::setBodyText
     * @see \Magento\Framework\Mail\Message::setBodyHtml
     */
    public function setBody($body)
    {
        if (is_string($body)) {
            $body = $this->createHtmlMimeFromString($body);
        }

        $this->zendMessage->setBody($body);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setSubject($subject)
    {
        $this->zendMessage->setSubject($subject);
        return $this;
    }

    /**
     * Create HTML mime message from the string.
     * @param string $htmlBody
     * @return \Zend\Mime\Message
     */
    private function createHtmlMimeFromString($htmlBody)
    {
        $htmlPart = new Part($htmlBody);
        $htmlPart->setCharset($this->zendMessage->getEncoding());
        $htmlPart->setType(Mime::TYPE_HTML);
        $mimeMessage = new \Zend\Mime\Message();
        $mimeMessage->addPart($htmlPart);

        $this->parts[] = $htmlPart;

        return $mimeMessage;
    }


    /**
     * {@inheritdoc}
     */
    public function getSubject()
    {
        return $this->zendMessage->getSubject();
    }

    /**
     * {@inheritdoc}
     */
    public function getBody()
    {
        return $this->zendMessage->getBody();
    }

    /**
     * {@inheritdoc}
     */
    public function setFrom($fromAddress)
    {
        $this->zendMessage->setFrom($fromAddress);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setFromAddress($fromAddress, $fromName = null)
    {
        $this->zendMessage->setFrom($fromAddress, $fromName);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addTo($toAddress)
    {
        $this->zendMessage->addTo($toAddress);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addCc($ccAddress)
    {
        $this->zendMessage->addCc($ccAddress);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addBcc($bccAddress)
    {
        $this->zendMessage->addBcc($bccAddress);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setReplyTo($replyToAddress)
    {
        $this->zendMessage->setReplyTo($replyToAddress);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRawMessage()
    {
        return $this->zendMessage->toString();
    }

    /**
     * @inheritDoc
     */
    public function setMessageType($type)
    {
        return $this;
    }
}

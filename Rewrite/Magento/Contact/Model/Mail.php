<?php

namespace Xigen\ContactAttachment\Rewrite\Magento\Contact\Model;

use Magento\Contact\Model\ConfigInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Area;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Xigen\ContactAttachment\Helper\Data;
use Magento\Framework\App\Filesystem\DirectoryList;
use Xigen\ContactAttachment\Mail\Message;

class Mail extends \Magento\Contact\Model\Mail
{

    const FOLDER_LOCATION = 'contactattachment';

    /**
     * @var ConfigInterface
     */
    private $contactsConfig;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var StateInterface
     */
    private $inlineTranslation;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UploaderFactory
     */
    private $fileUploaderFactory;

    /**
     * @var Filesystem
     */
    private $fileSystem;

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * Initialize dependencies.
     * @param ConfigInterface $contactsConfig
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param StoreManagerInterface|null $storeManager
     */
    public function __construct(
        ConfigInterface $contactsConfig,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManager = null,
        Filesystem $fileSystem,
        UploaderFactory $fileUploaderFactory,
        Data $dataHelper
    ) {
        $this->contactsConfig = $contactsConfig;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager ?: ObjectManager::getInstance()->get(StoreManagerInterface::class);
        $this->fileUploaderFactory = $fileUploaderFactory;
        $this->fileSystem = $fileSystem;
        $this->dataHelper = $dataHelper;
    }

    /**
     * Send email from contact form
     * @param string $replyTo
     * @param array $variables
     * @return void
     */
    public function send($replyTo, array $variables)
    {

        $filePath = null;
        $fileName = null;
        $uploaded = false;

        $attachment = !empty($_FILES['attachment']) ? $_FILES['attachment']['name'] : null;

        if ($attachment) {

            $upload = $this->fileUploaderFactory->create(['fileId' => 'attachment']);
            $upload->setAllowRenameFiles(true);
            $upload->setFilesDispersion(true);
            $upload->setAllowCreateFolders(true);
            $upload->setAllowedExtensions(['csv', 'jpg', 'jpeg', 'gif', 'png', 'pdf', 'doc', 'docx']);

            $path = $this->fileSystem
                ->getDirectoryRead(DirectoryList::MEDIA)
                ->getAbsolutePath(self::FOLDER_LOCATION);
            $result = $upload->save($path);
            $uploaded = self::FOLDER_LOCATION . $upload->getUploadedFilename();
            $filePath = $result['path'] . $result['file'];
            $fileName = $result['name'];
        }

        /** @see \Magento\Contact\Controller\Index\Post::validatedParams() */
        $replyToName = !empty($variables['data']['name']) ? $variables['data']['name'] : null;

        $this->inlineTranslation->suspend();
        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($this->contactsConfig->emailTemplate())
                ->setTemplateOptions(
                    [
                        'area' => Area::AREA_FRONTEND,
                        'store' => $this->storeManager->getStore()->getId(),
                    ]
                )
                ->setTemplateVars($variables)
                ->setFrom($this->contactsConfig->emailSender())
                ->addTo($this->contactsConfig->emailRecipient())
                ->setReplyTo($replyTo, $replyToName)
                ->getTransport();

            if ($uploaded) {
                $fileAttachment = $this->setAttachment($transport->getMessage(), $filePath);
                if ($this->dataHelper->versionCompare("2.3")) {
                    $transport->getMessage()->setBodyAttachment(
                        $fileAttachment->getContent(),
                        $fileAttachment->filename,
                        $fileAttachment->type,
                        \Zend_Mime::ENCODING_BASE64
                    );
                }
            }

            $transport->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    /**
     * @param Message $message
     * @param string $tacPath
     */
    private function setAttachment(Message $message, $path)
    {
        $fileInfo = pathinfo($path);
        $filePath = $path;
        $ext = $fileInfo['extension'] ?? null;
        $fileName = $fileInfo['filename'] ?? null;
        $mimeType = mime_content_type($path);

        if ($this->dataHelper->versionCompare("2.3")) {
            $attachment = new \Zend_Mime_Part(file_get_contents($filePath));
            $attachment->type = $mimeType;
            $attachment->disposition = \Zend_Mime::DISPOSITION_ATTACHMENT;
            $attachment->filename = $fileName . $ext;
            return $attachment;
        } else {
            $message->createAttachment(
                file_get_contents($filePath),
                $mimeType,
                \Zend_Mime::DISPOSITION_ATTACHMENT,
                \Zend_Mime::ENCODING_BASE64,
                $fileName . $ext
            );
        }
    }
}
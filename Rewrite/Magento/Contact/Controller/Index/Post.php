<?php


namespace Xigen\ContactAttachment\Rewrite\Magento\Contact\Controller\Index;

use \Magento\Store\Model\StoreManagerInterface;
use Magento\Contact\Model\ConfigInterface;
use Magento\Contact\Model\MailInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Translate\Inline\StateInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Xigen\ContactAttachment\Mail\Template\TransportBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Post controller class
 */
class Post extends \Magento\Contact\Controller\Index\Post
{
    const FOLDER_LOCATION = 'contactattachment';

    /**
     * @var DataPersistorInterface
     */
    private $dataPersistor;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var MailInterface
     */
    private $mail;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var UploaderFactory
     */
    private $fileUploaderFactory;

    /**
     * @var Filesystem
     */
    private $fileSystem;

    /**
     * @var StateInterface
     */
    private $inlineTranslation;

    /**
     * @var ConfigInterface
     */
    private $contactsConfig;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Post constructor.
     * @param Context $context
     * @param MailInterface $mail
     * @param DataPersistorInterface $dataPersistor
     * @param LoggerInterface|null $logger
     * @param UploaderFactory $fileUploaderFactory
     * @param Filesystem $fileSystem
     * @param StateInterface $inlineTranslation
     * @param ConfigInterface $contactsConfig
     * @param TransportBuilder $transportBuilder
     */
    public function __construct(
        Context $context,
        MailInterface $mail,
        DataPersistorInterface $dataPersistor,
        LoggerInterface $logger = null,
        UploaderFactory $fileUploaderFactory,
        Filesystem $fileSystem,
        StateInterface $inlineTranslation,
        ConfigInterface $contactsConfig,
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->context = $context;
        $this->mail = $mail;
        $this->dataPersistor = $dataPersistor;
        $this->logger = $logger ?: ObjectManager::getInstance()->get(LoggerInterface::class);
        $this->fileUploaderFactory = $fileUploaderFactory;
        $this->fileSystem = $fileSystem;
        $this->inlineTranslation = $inlineTranslation;
        $this->contactsConfig = $contactsConfig;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $contactsConfig, $mail, $dataPersistor, $logger);
    }

    /**
     * Post user question
     * @return Redirect
     */
    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        try {
            $this->sendEmail($this->validatedParams());
            $this->messageManager->addSuccessMessage(
                __('Thanks for contacting us with your comments and questions. We\'ll respond to you very soon.')
            );
            $this->dataPersistor->clear('contact_us');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->dataPersistor->set('contact_us', $this->getRequest()->getParams());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(
                __('An error occurred while processing your form. Please try again later.')
            );
            $this->dataPersistor->set('contact_us', $this->getRequest()->getParams());
        }
        return $this->resultRedirectFactory->create()->setPath('contact/index');
    }

    /**
     * @param array $post Post data from contact form
     * @return void
     */
    private function sendEmail($post)
    {
        $this->send(
            $post['email'],
            ['data' => new DataObject($post)]
        );
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
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $transport = $this->transportBuilder
            ->setTemplateIdentifier($this->scopeConfig->getValue(self::XML_PATH_EMAIL_TEMPLATE, $storeScope))
            ->setTemplateOptions(
                [
                    'area' => Area::AREA_FRONTEND,
                    'store' => $this->storeManager->getStore()->getId(),
                ]
            )
            ->setTemplateVars($variables)
            ->setFrom($this->scopeConfig->getValue(self::XML_PATH_EMAIL_SENDER, $storeScope))
            ->addTo($this->scopeConfig->getValue(self::XML_PATH_EMAIL_RECIPIENT, $storeScope))
            ->setReplyTo($replyTo)
            ->getTransport();

        if ($uploaded) {
            $fileInfo = pathinfo($path);
            $filePath = $path;
            $ext = $fileInfo['extension'] ?? null;
            $fileName = $fileInfo['filename'] ?? null;
            $mimeType = mime_content_type($path);

            $transport->addAttachment(
                file_get_contents($filePath),
                $fileName,
                $ext
            );
        }

        $transport->sendMessage();
        $this->inlineTranslation->resume();
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function validatedParams()
    {
        $request = $this->getRequest();
        if (trim($request->getParam('name')) === '') {
            throw new LocalizedException(__('Name is missing'));
        }
        if (trim($request->getParam('comment')) === '') {
            throw new LocalizedException(__('Comment is missing'));
        }
        if (false === \strpos($request->getParam('email'), '@')) {
            throw new LocalizedException(__('Invalid email address'));
        }
        if (trim($request->getParam('hideit')) !== '') {
            throw new \Exception();
        }

        return $request->getParams();
    }
}

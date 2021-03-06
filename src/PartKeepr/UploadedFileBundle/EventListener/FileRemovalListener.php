<?php
namespace PartKeepr\UploadedFileBundle\EventListener;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Dunglas\ApiBundle\Api\IriConverterInterface;
use PartKeepr\ImageBundle\Entity\Image;
use PartKeepr\ImageBundle\Entity\TempImage;
use PartKeepr\ImageBundle\Services\ImageService;
use PartKeepr\UploadedFileBundle\Entity\TempUploadedFile;
use PartKeepr\UploadedFileBundle\Entity\UploadedFile;
use PartKeepr\UploadedFileBundle\Services\UploadedFileService;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class FileRemovalListener
{
    /**
     * @var UploadedFileService
     */
    private $uploadedFileService;

    /**
     * @var ImageService
     */
    private $imageService;

    /**
     * @var Reader
     */
    private $reader;

    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    public function __construct(
        UploadedFileService $uploadedFileService,
        Reader $reader,
        PropertyAccessorInterface $propertyAccessor
    ) {
        $this->uploadedFileService = $uploadedFileService;
        $this->reader = $reader;
        $this->propertyAccessor = $propertyAccessor;
    }

    public function onFlush(OnFlushEventArgs $eventArgs) {
        $em = $eventArgs->getEntityManager();
        $uow = $em->getUnitOfWork();
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof UploadedFile) {
                $this->uploadedFileService->delete($entity);
            }
        }
    }
}

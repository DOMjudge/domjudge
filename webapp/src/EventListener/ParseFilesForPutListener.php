<?php declare(strict_types=1);

namespace App\EventListener;

use Riverline\MultiPartParser\Converters\HttpFoundation;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

#[AsEventListener(priority: 1)]
class ParseFilesForPutListener
{
    public function __invoke(ControllerEvent $event): void
    {
        // When we have a PUT request, the files on the request will not be filled, since PHP doesn't
        // fill the $_FILES array. We can manually parse the request to fill it using a library.
        // This code is based on https://github.com/symfony/symfony/issues/36409#issuecomment-612442260.
        $request = $event->getRequest();
        if ($request->isMethod('PUT')) {
            $document = HttpFoundation::convert($request);
            // Not all PUT requests are multipart, for example JSON PUT requests aren't. So check
            // to see if this is a multipart request and otherwise do nothing.
            if (!$document->isMultiPart()) {
                return;
            }

            foreach ($document->getParts() as $part) {
                if (!$part->isFile()) {
                    continue;
                }

                $filename = tempnam(sys_get_temp_dir(), 'dj-put');
                file_put_contents($filename, $part->getBody());
                $uploadedFile = new UploadedFile(
                    $filename,
                    $part->getFileName(),
                    $part->getMimeType(),
                    test: true // Since it is not a real uploaded file, mark it as test
                );
                $request->files->set($part->getName(), $uploadedFile);
            }
        }
    }
}

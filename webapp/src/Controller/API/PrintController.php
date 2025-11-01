<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\PrintTeam;
use App\Service\DOMJudgeService;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Language;

#[Route(path: "/printing")]
#[OA\Tag(name: "Printing")]
#[OA\Response(ref: "#/components/responses/Unauthenticated", response: 401)]
#[OA\Response(ref: "#/components/responses/Unauthorized", response: 403)]
class PrintController extends AbstractFOSRestController
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly DOMJudgeService $dj,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * Send a file to the system printer as a team.
     * @return JsonResponse
     */
    #[IsGranted("ROLE_TEAM")]
    #[Rest\Post("/team")]
    #[OA\Tag(name: "Printing")]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: new Model(type: PrintTeam::class))
    )]
    #[OA\Response(
        response: 200,
        description: "Returns the ID of the imported problem and any messages produced",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: "success",
                    description: "Whether printing was successful",
                    type: "boolean"
                ),
                new OA\Property(
                    property: "output",
                    description: "The print command output",
                    type: "string"
                ),
            ],
            type: "object"
        )
    )]
    #[OA\Response(
        response: 422,
        description: "An error occurred while processing the print job",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "errors", type: "object"),
            ]
        )
    )]
    public function printAsTeam(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        PrintTeam $print,
        Request $request
    ): JsonResponse {
        $langid = null;
        if ($print->language !== null) {
            $langid = $this->em
                ->createQueryBuilder()
                ->from(Language::class, "l")
                ->select("l.langid")
                ->andWhere("l.name = :name")
                ->setParameter("name", $print->language)
                ->getQuery()
                ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);
        }

        $decodedFile = base64_decode($print->fileContents, true);
        if ($decodedFile === false) {
            throw new BadRequestHttpException("The file contents is not base64 encoded.");
        }

        if (!($tempFilename = tempnam($this->dj->getDomjudgeTmpDir(), "printfile-"))) {
            throw new ServiceUnavailableHttpException(null, "Could not create temporary file.");
        }

        if (file_put_contents($tempFilename, $decodedFile) === false) {
            throw new ServiceUnavailableHttpException(
                null,
                sprintf("Could not write printfile to temporary file '%s'.", $tempFilename)
            );
        }

        $ret = $this->dj->printUserFile($tempFilename, $print->originalName, $langid, true);
        unlink($tempFilename);

        return new JsonResponse(
            ["success" => $ret[0], "output" => $ret[1]],
            $ret[0] ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE
        );
    }
}

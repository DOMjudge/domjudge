<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\ExecutableFile;
use App\Entity\ImmutableExecutable;
use App\Entity\Language;
use App\Utils\Utils;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use ZipArchive;

/**
 * @extends AbstractRestController<Language, Language>
 */
#[Rest\Route('/')]
#[OA\Tag(name: 'Languages')]
#[OA\Parameter(ref: '#/components/parameters/cid')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
class LanguageController extends AbstractRestController
{
    /**
     * Get all the languages for this contest.
     * @throws NonUniqueResultException
     */
    #[Rest\Get('languages')]
    // The languages endpoint doesn't require `cid` but the CLICS spec requires us to also expose it under a contest.
    #[Rest\Get('contests/{cid}/languages')]
    #[OA\Response(
        response: 200,
        description: 'Returns all the languages for this contest',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Language::class))
        )
    )]
    #[OA\Parameter(ref: '#/components/parameters/idlist')]
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given language for this contest.
     * @throws NonUniqueResultException
     */
    #[Rest\Get('languages/{id}')]
    // The languages endpoint doesn't require `cid` but the CLICS spec requires us to also expose it under a contest.
    #[Rest\Get('contests/{cid}/languages/{id}')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given language for this contest',
        content: new Model(type: Language::class)
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function singleAction(Request $request, string $id): Response
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * Update the executable of a language.
     * @throws NonUniqueResultException
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Post('languages/{id}/executable')]
    #[OA\Response(
        response: 200,
        description: 'Update the executable for a given language.',
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function updateExecutableActions(Request $request, string $id): void
    {
        $language = $this->em->getRepository(Language::class)->find($id);
        if (!$language) {
            throw new BadRequestHttpException("Unknown language '$id'.");
        }
        $file = $request->files->get('executable');
        if (empty($file)) {
            throw new BadRequestHttpException('executable file missing');
        }
        $zip = $this->dj->openZipFile($file->getRealPath());

        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            $content = $zip->getFromName($filename);
            if (empty($content)) {
                // Empty file or directory, ignore.
                continue;
            }
            $idx = count($files);
            $isExecutable = false;
            $opsys = $attr = 0;
            if ($zip->getExternalAttributesIndex($i, $opsys, $attr)
                && $opsys==ZipArchive::OPSYS_UNIX) {
                $attr = ($attr >> 16) & 0777;
                $isExecutable = ($attr & 0o100) == 0o100;
            }
            $executableFile = new ExecutableFile();
            $executableFile
                ->setRank($idx)
                ->setIsExecutable($isExecutable)
                ->setFilename($filename)
                ->setFileContent($content);
            $this->em->persist($executableFile);
            $files[] = $executableFile;
        }

        $immutableExecutable = new ImmutableExecutable($files);
        $this->em->persist($immutableExecutable);

        $language->getCompileExecutable()->setImmutableExecutable($immutableExecutable);
        $this->em->flush();
        $this->dj->auditlog('executable', $language->getLangid(), 'updated');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Post('languages')]
    #[OA\Response(
        response: 200,
        description: 'Configure all specified languages.',
    )]
    public function configureLanguagesAction(Request $request): Response
    {
        /** @var UploadedFile|null $jsonFile */
        $jsonFile = $request->files->get('json');
        if (!$jsonFile) {
            throw new BadRequestHttpException('No JSON file supplied.');
        }
        $newLanguages = Utils::jsonDecode(file_get_contents($jsonFile->getRealPath()));

        // Disable submission for all current languages, we will enable it for all new languages below.
        $curLanguages = $this->em->getRepository(Language::class)->findAll();
        foreach ($curLanguages as $language) {
            /** @var Language $language */
            $language->setAllowSubmit(false);
        }

        foreach ($newLanguages as $language) {
            /** @var Language $language */
            $lang_id = $language['id'];
            $lang = $this->em->getRepository(Language::class)->findOneBy(
                ['externalid' => $lang_id]
            );
            if (!$lang) {
                // TODO: Decide how to handle this case, either erroring out or creating a new language.
                continue;
            }

            // We disallowed submission for all languages above, so we need to enable it for the given languages.
            $lang->setAllowSubmit(true);

            if (isset($language['name'])) {
                $lang->setName($language['name']);
            }
            if (isset($language['allow_submit'])) {
                $lang->setAllowSubmit($language['allow_submit']);
            }
            if (isset($language['allow_judge'])) {
                $lang->setAllowJudge($language['allow_judge']);
            }
            if (isset($language['entry_point_required'])) {
                $lang->setRequireEntryPoint($language['entry_point_required']);
            }
            if (isset($language['entry_point_name'])) {
                $lang->setEntryPointDescription($language['entry_point_name']);
            }
            if (isset($language['extensions'])) {
                $lang->setExtensions($language['extensions']);
            }
            if (isset($language['filter_compiler_files'])) {
                $lang->setFilterCompilerFiles($language['filter_compiler_files']);
            }
            if (isset($language['time_factor'])) {
                $lang->setTimeFactor($language['time_factor']);
            }
            if (isset($language['compiler'])) {
                if (isset($language['compiler']['version_command'])) {
                    $lang->setCompilerVersionCommand($language['compiler']['version_command']);
                }
                if (isset($language['compiler']['version'])) {
                    $lang->setCompilerVersion($language['compiler']['version']);
                }
            }
            if (isset($language['runner'])) {
                if (isset($language['runner']['version_command'])) {
                    $lang->setRunnerVersionCommand($language['runner']['version_command']);
                }
                if (isset($language['runner']['version'])) {
                    $lang->setRunnerVersion($language['runner']['version']);
                }
            }
        }
        $this->em->flush();

        return parent::performListAction($request);
    }

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        if ($request->attributes->has('cid')) {
            // If a contest was passed, make sure that contest exists by calling getContestId.
            // Most API endpoints use the contest to filter its queries, but the languages endpoint does not. So we just
            // call it here.
            $this->getContestId($request);
        }
        return $this->em->createQueryBuilder()
            ->from(Language::class, 'lang')
            ->select('lang')
            ->andWhere('lang.allowSubmit = 1');
    }

    protected function getIdField(): string
    {
        return 'lang.externalid';
    }
}

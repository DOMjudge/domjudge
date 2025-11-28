<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Service\DOMJudgeService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[AsCommand(
    name: 'api:call',
    description: 'Call the DOMjudge API directly. Note: this will use admin credentials'
)]
readonly class CallApiActionCommand
{
    public function __construct(
        protected DOMJudgeService $dj,
        protected EntityManagerInterface $em
    ) {}

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $file
     */
    public function __invoke(
        #[Argument(description: 'The API endpoint to call. For example `contests/3/teams`')]
        string $endpoint,
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'POST data to use as key=value. Only allowed when the method is POST or PUT', shortcut: 'd')]
        array $data = [],
        #[Option(description: 'Files to use as field=filename. Only allowed when the method is POST or PUT', shortcut: 'f')]
        array $file = [],
        #[Option(description: 'User to use for API requests. If not given, the first admin user will be used', shortcut: 'u')]
        ?string $user = null,
        #[Option(description: 'JSON body data to use. Only allowed when the method is POST or PUT', shortcut: 'j')]
        ?string $json = null,
        #[Option(description: 'The HTTP method to use', shortcut: 'm')]
        string $method = Request::METHOD_GET,
    ): int {
        if (!in_array($method, [Request::METHOD_GET, Request::METHOD_POST, Request::METHOD_PUT], true)) {
            $output->writeln('Error: only GET, POST and PUT methods are supported');
            return Command::FAILURE;
        }

        if ($user) {
            $user = $this->em
                ->getRepository(User::class)
                ->findOneBy(['username' => $user]);
            if (!$user) {
                $output->writeln('Error: Provided user not found');
                return Command::FAILURE;
            }
        } else {
            // Find an admin user as we need one to make sure we can read all events.
            /** @var User|null $user */
            $user = $this->em->createQueryBuilder()
                ->from(User::class, 'u')
                ->select('u')
                ->join('u.user_roles', 'r')
                ->andWhere('r.dj_role = :role')
                ->setParameter('role', 'admin')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            if (!$user) {
                $output->writeln('Error: No admin user found. Please create at least one');
                return Command::FAILURE;
            }
        }

        $jsonData = [];
        $files = [];
        if (in_array($method, [Request::METHOD_POST, Request::METHOD_PUT], true)) {
            foreach ($data as $dataItem) {
                $parts = explode('=', $dataItem, 2);
                if (count($parts) !== 2) {
                    $output->writeln(sprintf('Error: data item %s is not in key=value format', $dataItem));
                    return Command::FAILURE;
                }

                $jsonData[$parts[0]] = $parts[1];
            }

            if ($json) {
                $jsonData = array_merge($jsonData, Utils::jsonDecode($json));
            }

            foreach ($file as $fileItem) {
                $parts = explode('=', $fileItem, 2);
                if (count($parts) !== 2) {
                    $output->writeln(sprintf('Error: file item %s is not in key=value format', $fileItem));
                    return Command::FAILURE;
                }

                if (!file_exists($parts[1])) {
                    $output->writeln(sprintf('Error: file %s does not exist', $parts[1]));
                    return Command::FAILURE;
                }

                $files[$parts[0]] = new UploadedFile($parts[1], basename($parts[1]), mime_content_type($parts[1]), null, true);
            }
        } else {
            if ($data) {
                $output->writeln('Error: data not allowed for GET methods.');
                return Command::FAILURE;
            }
            if ($file) {
                $output->writeln('Error: files not allowed for GET methods.');
                return Command::FAILURE;
            }
        }

        try {
            $response = '';
            $this->dj->withAllRoles(function () use ($method, $endpoint, $jsonData, $files, &$response): void {
                $response = $this->dj->internalApiRequest('/' . $endpoint, $method, $jsonData, $files, true);
            }, $user);
        } catch (HttpException $e) {
            $output->writeln($e->getMessage());
            // Return the HTTP status code as error code
            return Command::FAILURE;
        }

        $output->writeln($response);
        return Command::SUCCESS;
    }
}

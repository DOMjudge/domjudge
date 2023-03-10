<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CallApiActionCommand extends Command
{
    protected DOMJudgeService $dj;
    protected EntityManagerInterface $em;

    public function __construct(DOMJudgeService $dj, EntityManagerInterface $em)
    {
        parent::__construct();
        $this->dj = $dj;
        $this->em = $em;
    }

    protected function configure(): void
    {
        $this
            ->setName('api:call')
            ->setDescription('Call the DOMjudge API directly. Note: this will use admin credentials')
            ->addArgument(
                'endpoint',
                InputArgument::REQUIRED,
                'The API endpoint to call. For example `contests/3/teams`'
            )
            ->addOption(
                'method',
                'm',
                InputOption::VALUE_REQUIRED,
                'The HTTP method to use',
                Request::METHOD_GET
            )
            ->addOption(
                'data',
                'd',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'POST data to use as key=value. Only allowed when the method is POST or PUT'
            )
            ->addOption(
                'json',
                'j',
                InputOption::VALUE_REQUIRED,
                'JSON body data to use. Only allowed when the method is POST or PUT'
            )
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Files to use as field=filename. Only allowed when the method is POST or PUT'
            )
            ->addOption(
                'user',
                'u',
                InputOption::VALUE_REQUIRED,
                'User to use for API requests. If not given, the first admin user will be used'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!in_array($input->getOption('method'), [Request::METHOD_GET, Request::METHOD_POST, Request::METHOD_PUT], true)) {
            $output->writeln('Error: only GET, POST and PUT methods are supported');
            return self::FAILURE;
        }

        if ($input->getOption('user')) {
            $user = $this->em
                ->getRepository(User::class)
                ->findOneBy(['username' => $input->getOption('user')]);
            if (!$user) {
                $output->writeln('Error: Provided user not found');
                return self::FAILURE;
            }
        } else {
            // Find an admin user as we need one to make sure we can read all events.
            /** @var User $user */
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
                return self::FAILURE;
            }
        }

        $data = [];
        $files = [];
        if (in_array($input->getOption('method'), [Request::METHOD_POST, Request::METHOD_PUT], true)) {
            foreach ($input->getOption('data') as $dataItem) {
                $parts = explode('=', $dataItem, 2);
                if (count($parts) !== 2) {
                    $output->writeln(sprintf('Error: data item %s is not in key=value format', $dataItem));
                    return self::FAILURE;
                }

                $data[$parts[0]] = $parts[1];
            }

            if ($json = $input->getOption('json')) {
                $data = array_merge($data, $this->dj->jsonDecode($json));
            }

            foreach ($input->getOption('file') as $fileItem) {
                $parts = explode('=', $fileItem, 2);
                if (count($parts) !== 2) {
                    $output->writeln(sprintf('Error: file item %s is not in key=value format', $fileItem));
                    return self::FAILURE;
                }

                if (!file_exists($parts[1])) {
                    $output->writeln(sprintf('Error: file %s does not exist', $parts[1]));
                    return self::FAILURE;
                }

                $files[$parts[0]] = new UploadedFile($parts[1], basename($parts[1]), mime_content_type($parts[1]), null, true);
            }
        } else {
            if ($input->getOption('data')) {
                $output->writeln('Error: data not allowed for GET methods.');
                return self::FAILURE;
            }
            if ($input->getOption('file')) {
                $output->writeln('Error: files not allowed for GET methods.');
                return self::FAILURE;
            }
        }

        try {
            $response = null;
            $this->dj->withAllRoles(function () use ($input, $data, $files, &$response) {
                $response = $this->dj->internalApiRequest('/' . $input->getArgument('endpoint'), $input->getOption('method'), $data, $files, true);
            }, $user);
        } catch (HttpException $e) {
            $output->writeln($e->getMessage());
            // Return the HTTP status code as error code
            return self::FAILURE;
        }

        $output->writeln(json_encode($response, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}

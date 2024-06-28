<?php declare(strict_types=1);

namespace App\Command;

use App\Service\Compare\AbstractCompareService;
use App\Service\Compare\Message;
use App\Service\Compare\MessageType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @template T
 */
abstract class AbstractCompareCommand extends Command
{
    /**
     * @param AbstractCompareService<T> $compareService
     */
    public function __construct(
        protected readonly SerializerInterface $serializer,
        protected AbstractCompareService $compareService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file1', InputArgument::REQUIRED, 'First file to compare')
            ->addArgument('file2', InputArgument::REQUIRED, 'Second file to compare');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $messages = $this->compareService->compareFiles($input->getArgument('file1'), $input->getArgument('file2'));

        return $this->displayMessages($style, $messages) ?? Command::SUCCESS;
    }

    /**
     * @param Message[] $messages
     */
    protected function displayMessages(SymfonyStyle $style, array $messages): ?int
    {
        if (empty($messages)) {
            $style->success('Files match fully');
            return null;
        }

        $headers = ['Level', 'Message', 'Source', 'Target'];
        $rows = [];
        $counts = [];
        foreach ($messages as $message) {
            if (!isset($counts[$message->type->value])) {
                $counts[$message->type->value] = 0;
            }
            $counts[$message->type->value]++;
            $rows[] = [
                $this->formatMessage($message->type, $message->type->value),
                $this->formatMessage($message->type, $message->message),
                $this->formatMessage($message->type, $message->source ?? ''),
                $this->formatMessage($message->type, $message->target ?? ''),
            ];
        }
        $style->table($headers, $rows);

        $style->newLine();
        foreach ($counts as $type => $count) {
            $style->writeln($this->formatMessage(MessageType::from($type), sprintf('Found %d %s(s)', $count, $type)));
        }

        if (isset($counts['error'])) {
            $style->error('Files have potential critical differences');
            return Command::FAILURE;
        }

        $style->success('Files have differences but probably non critical');

        return null;
    }

    protected function formatMessage(MessageType $level, string $message): string
    {
        $colors = [
            MessageType::ERROR->value => 'red',
            MessageType::WARNING->value => 'yellow',
            MessageType::INFO->value => 'green',
        ];
        return sprintf('<fg=%s>%s</>', $colors[$level->value], $message);
    }
}

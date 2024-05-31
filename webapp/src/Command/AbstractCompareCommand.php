<?php declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractCompareCommand extends Command
{
    public function __construct(protected readonly SerializerInterface $serializer)
    {
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
        $messages = [];
        $success = true;
        if (!file_exists($input->getArgument('file1'))) {
            $this->addMessage($messages, $success, 'error', sprintf('File "%s" does not exist', $input->getArgument('file1')));
        }
        if (!file_exists($input->getArgument('file2'))) {
            $this->addMessage($messages, $success, 'error', sprintf('File "%s" does not exist', $input->getArgument('file2')));
        }
        if (!$success) {
            return $this->displayMessages($style, $messages);
        }

        $this->compare($messages, $success, $input->getArgument('file1'), $input->getArgument('file2'));

        return $this->displayMessages($style, $messages) ?? Command::SUCCESS;
    }

    /**
     * @param array<array{type: string, message: string, source: ?string, target: ?string}> $messages
     */
    abstract protected function compare(
        array &$messages,
        bool &$success,
        string $file1,
        string $file2,
    ): void;

    /**
     * @param array<array{type: string, message: string, source: ?string, target: ?string}> $messages
     */
    protected function addMessage(
        array &$messages,
        bool &$success,
        string $type,
        string $message,
        ?string $source = null,
        ?string $target = null,
    ): void {
        $messages[] = [
            'type' => $type,
            'message' => $message,
            'source' => $source,
            'target' => $target,
        ];
        if ($type === 'error') {
            $success = false;
        }
    }

    /**
     * @param array<array{type: string, message: string, source: ?string, target: ?string}> $messages
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
            if (!isset($counts[$message['type']])) {
                $counts[$message['type']] = 0;
            }
            $counts[$message['type']]++;
            $rows[] = [
                $this->formatMessage($message['type'], $message['type']),
                $this->formatMessage($message['type'], $message['message']),
                $this->formatMessage($message['type'], $message['source'] ?? ''),
                $this->formatMessage($message['type'], $message['target'] ?? ''),
            ];
        }
        $style->table($headers, $rows);

        $style->newLine();
        foreach ($counts as $type => $count) {
            $style->writeln($this->formatMessage($type, sprintf('Found %d %s(s)', $count, $type)));
        }

        if (isset($counts['error'])) {
            $style->error('Files have potential critical differences');
            return Command::FAILURE;
        }

        $style->success('Files have differences but probably non critical');

        return null;
    }

    protected function formatMessage(string $level, string $message): string
    {
        $colors = [
            'error' => 'red',
            'warning' => 'yellow',
            'info' => 'green',
        ];
        return sprintf('<fg=%s>%s</>', $colors[$level], $message);
    }
}

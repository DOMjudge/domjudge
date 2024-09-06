<?php declare(strict_types=1);

namespace App\Service\Compare;

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @template T
 */
abstract class AbstractCompareService
{
    /** @var Message[] */
    protected array $messages = [];

    public function __construct(protected readonly SerializerInterface $serializer) {}

    /**
     * @return Message[]
     */
    public function compareFiles(string $file1, string $file2): array
    {
        $success = true;
        if (!file_exists($file1)) {
            $this->addMessage(MessageType::ERROR, sprintf('File "%s" does not exist', $file1));
            $success = false;
        }
        if (!file_exists($file2)) {
            $this->addMessage(MessageType::ERROR, sprintf('File "%s" does not exist', $file2));
            $success = false;
        }
        if (!$success) {
            return $this->messages;
        }

        try {
            $object1 = $this->parseFile($file1);
        } catch (ExceptionInterface $e) {
            $this->addMessage(MessageType::ERROR, sprintf('Error deserializing file "%s": %s', $file1, $e->getMessage()));
        }
        try {
            $object2 = $this->parseFile($file2);
        } catch (ExceptionInterface $e) {
            $this->addMessage(MessageType::ERROR, sprintf('Error deserializing file "%s": %s', $file2, $e->getMessage()));
        }

        if (!isset($object1) || !isset($object2)) {
            return $this->messages;
        }

        $this->compare($object1, $object2);

        return $this->messages;
    }

    /**
     * @return T|null
     * @throws ExceptionInterface
     */
    abstract protected function parseFile(string $file);

    /**
     * @param T $object1
     * @param T $object2
     */
    abstract public function compare($object1, $object2): void;

    protected function addMessage(
        MessageType $type,
        string $message,
        ?string $source = null,
        ?string $target = null,
    ): void {
        $this->messages[] = new Message($type, $message, $source, $target);
    }

    /**
     * @return Message[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}

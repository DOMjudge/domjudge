<?php declare(strict_types=1);

namespace App\Command;

use App\Service\ConfigurationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class CheckDatabaseConfigurationDefaultValuesCommand
 *
 * @package App\Command
 */
class CheckDatabaseConfigurationDefaultValuesCommand extends Command
{
    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * CheckDatabaseConfigurationDefaultValuesCommand constructor.
     *
     * @param ConfigurationService $config
     * @param string|null          $name
     */
    public function __construct(
        ConfigurationService $config,
        string $name = null
    ) {
        parent::__construct($name);
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setName('domjudge:db-config:check')
            ->setDescription(
                'Check if the default values of the database configuration are valid'
            );
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style    = new SymfonyStyle($input, $output);
        $messages = [];
        foreach ($this->config->getConfigSpecification() as $specification) {
            $message = sprintf(
                'Configuration %s (in category %s) is of type %s but has wrong type for default_value (%s)',
                $specification['name'], $specification['category'],
                $specification['type'],
                json_encode($specification['default_value'])
            );
            switch ($specification['type']) {
                case 'bool':
                    if (!is_bool($specification['default_value'])) {
                        $messages[] = $message;
                    }
                    break;
                case 'int':
                    if (!is_int($specification['default_value'])) {
                        $messages[] = $message;
                    }
                    break;
                case 'string':
                    if (!is_string($specification['default_value'])) {
                        $messages[] = $message;
                    }
                    break;
                case 'array_val':
                    if (!(empty($specification['default_value']) || (
                            is_array($specification['default_value']) &&
                            is_int(key($specification['default_value']))))) {
                        $messages[] = $message;
                    }
                    break;
                case 'array_keyval':
                    if (!(empty($specification['default_value']) || (
                            is_array($specification['default_value']) &&
                            is_string(key($specification['default_value']))))) {
                        $messages[] = $message;
                    }
                    break;
            }
        }
        if (empty($messages)) {
            $style->success('All default values have the correct type');
        } else {
            $style->error('Some default values have the wrong type:');
            $style->listing($messages);
        }
    }
}

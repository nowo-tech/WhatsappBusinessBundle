<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Command;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;

/**
 * List message templates for a profile (requires WABA id).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(name: 'whatsapp:templates', description: 'List WhatsApp message templates for a profile')]
final class ListTemplatesCommand extends Command
{
    /**
     * Creates the command with the profile registry.
     */
    public function __construct(
        private readonly WhatsappClientRegistryInterface $registry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('profile', 'p', InputOption::VALUE_REQUIRED, 'Named profile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profile = $input->getOption('profile');
        $name    = is_string($profile) && $profile !== '' ? $profile : null;
        foreach ($this->registry->get($name)->listTemplates() as $template) {
            $output->writeln($template->name . ' ' . $template->status->value . ' ' . $template->language);
        }

        return Command::SUCCESS;
    }
}

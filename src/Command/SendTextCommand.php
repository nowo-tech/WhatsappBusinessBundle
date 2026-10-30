<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Command;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;

/**
 * Send a text message through a named profile.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(name: 'whatsapp:send-text', description: 'Send a WhatsApp Cloud API text message')]
final class SendTextCommand extends Command
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
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Recipient phone (E.164, with or without +)')
            ->addArgument('body', InputArgument::REQUIRED, 'Message body')
            ->addOption('profile', 'p', InputOption::VALUE_REQUIRED, 'Named profile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $to      = (string) $input->getArgument('to');
        $body    = (string) $input->getArgument('body');
        $profile = $input->getOption('profile');
        $name    = is_string($profile) && $profile !== '' ? $profile : null;
        $result  = $this->registry->get($name)->sendText($to, $body);
        $output->writeln($result->messageId);

        return Command::SUCCESS;
    }
}

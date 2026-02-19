<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:test-email',
    description: 'Send a test email to verify mailer configuration',
)]
class TestEmailCommand extends Command
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(MailerInterface $mailer, LoggerInterface $logger)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Sending test email...');
        $this->logger->info('Test email command started');

        try {
            $email = (new Email())
                ->from('noreply@wannasni.com')
                ->to('azizmehrez050@gmail.com')
                ->subject('Test Email from WANNASNI')
                ->text('This is a test email to verify the mailer configuration.')
                ->html('<p>This is a <strong>test email</strong> to verify the mailer configuration.</p>');

            $this->logger->info('Email object created, attempting to send...');
            $output->writeln('Email object created, sending...');

            $this->mailer->send($email);

            $this->logger->info('Test email sent successfully');
            $output->writeln('<info>✓ Email sent successfully!</info>');
            
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send test email', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            
            $output->writeln('<error>✗ Failed to send email:</error>');
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $output->writeln('<error>Exception: ' . get_class($e) . '</error>');
            
            return Command::FAILURE;
        }
    }
}

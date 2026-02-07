<?php

namespace App\Command;

use App\Service\EmailService;
use App\Service\InterventionPdfGeneratorService;
use App\Entity\Intervention;
use App\Entity\ServiceRequest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TestEmailCommand extends Command
{
    protected static $defaultName = 'app:test-email';
    protected static $defaultDescription = 'Test email sending functionality';
    
    private EmailService $emailService;
    private InterventionPdfGeneratorService $pdfGenerator;

    public function __construct(EmailService $emailService, InterventionPdfGeneratorService $pdfGenerator)
    {
        $this->emailService = $emailService;
        $this->pdfGenerator = $pdfGenerator;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Test email sending with PDF attachment')
            ->setHelp('This command tests the email functionality by creating a mock intervention and sending an email with PDF attachment.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing Email Functionality');

        try {
            // Create a mock intervention for testing
            $intervention = $this->createMockIntervention();

            $io->section('Generating PDF...');
            $pdfContent = $this->pdfGenerator->generatePdf($intervention);
            $io->success('PDF generated successfully');

            $io->section('Sending email...');
            $this->emailService->sendInterventionQuote(
                'baccourroua8@gmail.com', // Test email address
                'Test Client',
                $pdfContent,
                $intervention->getId()
            );

            $io->success('Email sent successfully! Check your inbox (or Mailtrap if configured)');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to send email: ' . $e->getMessage());
            $io->note('Make sure MAILER_DSN is configured in your .env file');

            return Command::FAILURE;
        }
    }

    private function createMockIntervention(): Intervention
    {
        $service = new ServiceRequest();
        $service->setTypeService('Test Service');
        $service->setDescription('Test service description');
        $service->setVille('Test City');
        $service->setSeniorEmail('test@example.com');

        $intervention = new Intervention();
        $intervention->setServiceRequest($service);
        $intervention->setTechnicienNom('Test Technician');
        $intervention->setTechnicienEmail('tech@test.com');
        $intervention->setTechnicienTelephone('01 23 45 67 89');
        $intervention->setCompetences('Test Skills');
        $intervention->setTarifHoraire(25.00);
        $intervention->setHeuresTravail(2);
        $intervention->setTypesServices('Test Service');
        $intervention->setZoneIntervention('Test Zone');
        $intervention->setStatutActuel('assignee');
        $intervention->setNotes('Test notes');
        $intervention->setDateCreation(new \DateTime());

        // Set a mock ID using reflection for testing
        $reflection = new \ReflectionClass($intervention);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($intervention, 999);

        return $intervention;
    }
}

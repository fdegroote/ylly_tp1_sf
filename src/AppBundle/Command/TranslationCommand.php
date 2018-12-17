<?php

namespace AppBundle\Command;

use AppBundle\Service\TranslationService;
use Doctrine\ORM\EntityManager;
use Google\Cloud\Translate\TranslateClient;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TranslationCommand extends ContainerAwareCommand
{
    /** @var TranslateClient $translate */
    protected $translate;

    /** @var EntityManager $em */
    protected $em;

    protected function configure()
    {
        $this
            ->setName('tp1:translate:auto')
            ->setDescription('Translate automatically content')
            ->addArgument('entityName', InputArgument::REQUIRED, 'select one entity')
            ->addArgument('language', InputArgument::REQUIRED, 'select one language')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Recover the lanaguage
        $language = $input->getArgument('language');

        $entityName = $input->getArgument('entityName');

        // Message Start command
        $output->writeln('Start traduct');

        // Start traduction in terms of language
        $this->getContainer()->get('translation.service')->translate($entityName, $language);

        // Message End command
        $output->writeln('');
        $output->writeln('End traduct');

    }

}
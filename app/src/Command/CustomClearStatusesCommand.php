<?php

namespace App\Command;

use App\Entity\Child;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\RouterInterface;

class CustomClearStatusesCommand extends Command
{
    protected static $defaultName = 'app:clear:statuses';
    protected static $defaultDescription = 'Очищает статусы детей';
    protected EntityManagerInterface $em;
    protected ParameterBagInterface $parameterBag;
    protected MailerInterface $mailer;
    protected RouterInterface $router;

    /** @required */
    public function setService(EntityManagerInterface $em, ParameterBagInterface $parameterBag, MailerInterface $mailer, RouterInterface $router): void
    {
        $this->em = $em;
        $this->parameterBag = $parameterBag;
        $this->mailer = $mailer;
        $this->router = $router;
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->text('Запрашиваю данные...');
        /** @var Child[] $childs */
        $childs = $this->em->getRepository(Child::class)->findAll();
        $io->text('Найдено детей: ' . count($childs));
        $io->text('Начинаю очистку статусов...');
        $io->progressStart(count($childs));
        foreach ($childs as $child) {
            $child->setIsPresent(null);
            $this->em->persist($child);
            $io->progressAdvance();
            usleep(50000);
        }
        $io->progressFinish();
        $io->text('Статусы очищены');
        $io->text('Сохраняю изменения в БД...');
        $this->em->flush();
        $io->text('Изменения сохранены');
        $io->success('Работа окончена!');

        return Command::SUCCESS;
    }
}

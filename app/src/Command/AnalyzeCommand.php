<?php

namespace App\Command;

use App\Entity\Child;
use App\Entity\Manager;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\Pure;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class AnalyzeCommand extends Command
{
    use LockableTrait;

    protected const reportsDir = '/reports';
    protected const analyzeGroups = ['not_group', 'not_checked', 'not_present', 'is_present',];
    protected const groupStatus = ['not_group' => 'Не назначен в группу', 'not_checked' => 'Не отмечен', 'not_present' => 'Отсутствует', 'is_present' => 'Присутствует',];
    protected static $defaultName = 'custom:analyze';
    protected static $defaultDescription = 'Анализ данных, подготовка и отправка отчета о списочном составе';
    protected EntityManagerInterface $em;
    protected ParameterBagInterface $parameterBag;
    protected MailerInterface $mailer;
    protected RouterInterface $router;
    protected \DateTime $time;
    protected SymfonyStyle $io;

    /** @required */
    public function setService(EntityManagerInterface $em, ParameterBagInterface $parameterBag, MailerInterface $mailer, RouterInterface $router)
    {
        $this->em = $em;
        $this->parameterBag = $parameterBag;
        $this->mailer = $mailer;
        $this->router = $router;
    }

    protected function configure(): void
    {
        $this->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        if (!$this->lock()) {
            $this->io->text('The command is already running in another process.');
            return Command::SUCCESS;
        }
        $this->time = (new \DateTime())->setDate(2021, 5, 29);
        $reportDir = $this->getReportDir();
        $reportFileName = 'kind_report_' . $this->time->format('YmdHis') . '.xls';
        $reportFilePath = $reportDir . '/' . $reportFileName;
        $reportUrl = $this->router->generate('report_download', ['slug' => $reportFileName], UrlGeneratorInterface::ABSOLUTE_URL);
        $reportTitle = 'Отчет о списочном составе на ' . $this->time->format('d.m.Y H:i:s');

        $this->io->text('Запрашиваю данные...');
        $childs = $this->em->getRepository(Child::class)->findBy([], ['kind_group' => 'ASC', 'last_name' => 'ASC']);
        $this->io->text('Найдено детей: ' . count($childs));
        $this->io->text('Начинаю анализ...');
        $groupedChilds = $this->getGroupedChilds($childs);
        $this->io->text('Анализ завершен');
        $this->io->text('Предварительные результаты:');
        $this->printReport($groupedChilds);
        $this->io->text('Собираю полный отчет...');
        $report = $this->generateReport($groupedChilds);
        $report->getProperties()->setTitle($reportTitle);
        $this->io->text('Отчет сформирован');
        IOFactory::createWriter($report, 'Xls')->save($reportFilePath);
        $this->io->text('Отчет сохранен, URL: ' . $reportUrl);
        $this->io->text('Собираю почтовые адреса...');
        $targetEmails = $this->getTargetEmails($groupedChilds);
        $this->io->text('Адресаты: ' . implode(', ', $targetEmails));
        $this->io->text('Отправляю письма...');
        $this->sendMail($reportTitle, $this->getEmailReport($groupedChilds, $reportUrl), $targetEmails, $reportFilePath);
        $this->io->text('Отправлено писем: ' . count($targetEmails));
        $this->io->success('Работа окончена!');

        return Command::SUCCESS;
    }

    /**
     * Производит анализ данных по ребенку
     * @param Child $child данные ребенка
     * @return string выявленная категория
     */
    #[Pure] protected function analyzeChildData(Child $child): string
    {
        return match (true) {
            //ребенка забыли зачислить в группу
            $child->getKindGroup() === null => 'not_group',
            //ребенка забыли отметить
            $child->getIsPresent() === null => 'not_checked',
            //ребенок отмечен как отсутствующий
            $child->getIsPresent() === false => 'not_present',
            //ребенок отмечен как присутствующий
            $child->getIsPresent() === true => 'is_present',
        };
    }

    /**
     * @param Child[] $childs
     * @return array = ['not_group'=>[], 'not_checked'=>[], 'not_present'=>[], 'is_present'=>[]]
     */
    #[Pure] protected function getGroupedChilds(array $childs): array
    {
        //разбиваем детей на категории
        $groupedChilds = array_combine(self::analyzeGroups, array_fill(0, count(self::analyzeGroups), []));
        foreach ($childs as $child) {
            $group = $this->analyzeChildData($child);
            $groupedChilds[$group][] = $child;
        }

        return $groupedChilds;
    }

    protected function getTargetEmails(array $groupedChilds): array
    {
        /** @var Manager[] $managers */
        $managers = $this->em->getRepository(Manager::class)->findAll();

        //собираем почтовые ящики тех, кому нужно отправить отчет
        $targetEmails = [];
        //обязательно отправляем отчет заведующим
        foreach ($managers as $manager) {
            $targetEmails[] = $manager->getUser()->getEmail();
        }
        //также отправим воспитателям проблемных групп
        foreach ($groupedChilds['not_checked'] as $child) {
            foreach ($child->getKindGroup()->getTeachers() as $teacher) {
                $targetEmails[] = $teacher->getUser()->getEmail();
            }
        }

        return array_unique($targetEmails);
    }

    protected function getEmailReport(array $groupedChilds, string $reportUrl): string
    {
        $totalChilds = array_reduce($groupedChilds, function ($carry, $childs) {
            return $carry + count($childs);
        }, 0);
        $isPresentCount = count($groupedChilds['is_present']);
        $notPresentCount = count($groupedChilds['not_present']);
        $notCheckedCount = count($groupedChilds['not_checked']);
        $notInGroupCount = count($groupedChilds['not_group']);

        $text = 'Статистика:' . PHP_EOL;
        $text .= 'Всего детей: ' . $totalChilds . PHP_EOL;
        $text .= 'Присутствуют: ' . $isPresentCount . PHP_EOL;
        $text .= 'Отсутствуют: ' . $notPresentCount . PHP_EOL;
        $text .= 'Не отмечено: ' . $notCheckedCount . PHP_EOL;
        $text .= 'Не назначено в группу: ' . $notInGroupCount . PHP_EOL;
        $text .= 'Полный отчет, URL: ' . $reportUrl . PHP_EOL;
        $text .= '==============================' . PHP_EOL;
        foreach (self::analyzeGroups as $group) {
            if ($group === 'is_present') continue;
            foreach ($groupedChilds[$group] as $child) {
                $childInfo = $child->getLastName() . ' ' . $child->getFirstName();
                if ($group !== 'not_group') $childInfo .= ', группа ' . $child->getKindGroup()->getName();
                $status = self::groupStatus[$group];
                $text .= $status . ': ' . $childInfo . PHP_EOL;
            }
        }
        return $text;
    }

    protected function printReport(array $groupedChilds): void
    {
        $totalChilds = array_reduce($groupedChilds, function ($carry, $childs) {
            return $carry + count($childs);
        }, 0);
        $isPresentCount = count($groupedChilds['is_present']);
        $notPresentCount = count($groupedChilds['not_present']);
        $notCheckedCount = count($groupedChilds['not_checked']);
        $notInGroupCount = count($groupedChilds['not_group']);

        $this->io->text('==============================');
        foreach (array_reverse(self::analyzeGroups) as $group) {
            if ($group === 'is_present') continue;
            foreach ($groupedChilds[$group] as $child) {
                usleep(rand(50000, 500000));
                $childInfo = $child->getLastName() . ' ' . $child->getFirstName();
                if ($group !== 'not_group') $childInfo .= ', группа ' . $child->getKindGroup()->getName();
                $status = self::groupStatus[$group];
                $this->io->text($status . ': ' . $childInfo);
            }
        }

        $this->io->text('==============================');
        $this->io->text('Итого:');
        $this->io->text('Всего детей: ' . $totalChilds);
        $this->io->text('Присутствуют: ' . $isPresentCount);
        if ($notPresentCount) $this->io->text('Отсутствуют: ' . $notPresentCount);
        if ($notCheckedCount) $this->io->text('Не отмечено: ' . $notCheckedCount);
        if ($notInGroupCount) $this->io->text('Не назначено в группу: ' . $notInGroupCount);
        $this->io->text('==============================');
    }

    protected function generateReport(array $groupedChilds): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach (['A1' => 'Группа', 'B1' => 'Фамилия', 'C1' => 'Имя', 'D1' => 'Статус'] as $cord => $name) {
            $sheet->setCellValue($cord, $name);
            $sheet->getStyle($cord)->getFont()->setBold(true);
            $sheet->getStyle($cord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($cord)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
        }
        $colors = array_combine(self::analyzeGroups, ['ffffeabd', 'ffffbdbd', 'fffffebd',
            'ffbdffbd']);
        $row = 1;
        foreach (self::analyzeGroups as $group) {
            foreach ($groupedChilds[$group] as $child) {
                $row++;
                if ($group !== 'not_group') {
                    $sheet->setCellValue('A' . $row, $child->getKindGroup()->getName());
                }
                $sheet->setCellValue('B' . $row, $child->getLastName());
                $sheet->setCellValue('C' . $row, $child->getFirstName());
                $sheet->setCellValue('D' . $row, self::groupStatus[$group]);
                $color = new Color($colors[$group]);
                $sheet->getStyle('D' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor($color);
            }
        }

        foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
            foreach (range(1, $sheet->getHighestDataRow()) as $row) {
                $sheet->getRowDimension($row)->setRowHeight($sheet->getRowDimension($row)->getRowHeight() + 5);
            }
        }

        return $spreadsheet;
    }

    protected function getReportDir(): string
    {
        $rootDir = $this->parameterBag->get('kernel.project_dir') . '/public';
        $reportDir = $rootDir . self::reportsDir;

        if (!is_dir($reportDir)) {
            if (!mkdir($reportDir) && !is_dir($reportDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $reportDir));
            }
        }

        return $reportDir;
    }

    protected function sendMail(string $subject, string $text, array $toEmails, string $attachFile): void
    {
        $isProd = $this->parameterBag->get('kernel.environment') === 'prod';

        $email = new Email();
        $email->to($isProd ? $toEmails : $toEmails[0]);
        $email->subject($subject);
        $email->text($text);
        $email->attachFromPath($attachFile);

        $this->mailer->send($email);
    }
}

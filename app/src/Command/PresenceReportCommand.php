<?php

namespace App\Command;

use App\Entity\Child;
use App\Entity\KindGroup;
use App\Entity\Manager;
use App\Entity\PresenceHistory;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PresenceReportCommand extends Command
{
    use LockableTrait;

    protected const reportsDir = '/reports';
    protected const months = [
        1 => 'Январь',
        2 => 'Февраль',
        3 => 'Март',
        4 => 'Апрель',
        5 => 'Май',
        6 => 'Июнь',
        7 => 'Июль',
        8 => 'Август',
        9 => 'Сентябрь',
        10 => 'Октябрь',
        11 => 'Ноябрь',
        12 => 'Декабрь',
    ];
    protected const minimalPercent = 70;

    protected static $defaultName = 'app:presence:report';
    protected static $defaultDescription = 'Анализ данных, подготовка и отправка отчета по истории посещаемости';
    protected EntityManagerInterface $em;
    protected ParameterBagInterface $parameterBag;
    protected MailerInterface $mailer;
    protected RouterInterface $router;
    protected \DateTime $time;
    protected SymfonyStyle $io;

    /**
     * @var KindGroup[]
     */
    protected $groups;
    /**
     * @var Child[]
     */
    protected $childs;
    /**
     * @var PresenceHistory[]
     */
    protected $records;
    protected $analyzedData = [
        'year' => 0,
        'monthy' => [],
        'group' => [],
        'child' => [],
    ];

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
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        if (!$this->lock()) {
            $this->io->text('The command is already running in another process.');
            return Command::SUCCESS;
        }
        $this->time = new \DateTime();
        $reportDir = $this->getReportDir();
        $reportFileName = 'Presence_report_' . $this->time->format('YmdHis') . '.xls';
        $reportFilePath = $reportDir . '/' . $reportFileName;
        $reportUrl = $this->router->generate('report_download', ['slug' => $reportFileName], UrlGeneratorInterface::ABSOLUTE_URL);
        $reportTitle = 'Отчет по истории посещаемости на ' . $this->time->format('d.m.Y H:i:s');
        $thisYear = (int)$this->time->format('Y');

        $this->io->text('Запрашиваю данные...');
        $this->groups = $this->em->getRepository(KindGroup::class)->findAll();
        $this->io->text('Найдено групп: ' . count($this->groups));
        $this->childs = $this->em->getRepository(Child::class)->findBy([], ['kind_group' => 'ASC', 'last_name' => 'ASC']);
        $this->io->text('Найдено детей: ' . count($this->childs));
        $this->records = $this->em->getRepository(PresenceHistory::class)->findByYear($thisYear);
        $this->io->text('Найдено записей истории за ' . $thisYear . ' год: ' . count($this->records));

        $this->io->text('Начинаю анализ...');
        $this->analyzedData['year'] = $this->analyzeByYear();
        $this->analyzedData['monthy'] = $this->analyzeByMonth();
        $this->analyzedData['group'] = $this->analyzeByGroup();
        $this->analyzedData['child'] = $this->analyzeByChild();
        $this->io->text('Анализ завершен');

        $this->io->text('Предварительные результаты:');
        $this->printReport();

        $this->io->text('Собираю полный отчет...');
        $report = $this->generateReport();
        $report->getProperties()->setTitle($reportTitle);
        $this->io->text('Отчет сформирован');
        IOFactory::createWriter($report, 'Xls')->save($reportFilePath);
        $this->io->text('Отчет сохранен, URL: ' . $reportUrl);

        $this->io->text('Собираю почтовые адреса...');
        $targetEmails = $this->getTargetEmails();
        $this->io->text('Адресаты: ' . implode(', ', $targetEmails));

        $this->io->text('Отправляю письма...');
        $this->sendMail($reportTitle, $this->getEmailReport($reportUrl), $targetEmails, $reportFilePath);
        $this->io->text('Отправлено писем: ' . count($targetEmails));

        $this->io->success('Работа окончена!');

        //на листе сверху вниз: процент за год, проценты по месяцам, проценты по группам, проценты по детям
        return Command::SUCCESS;
    }

    protected function analyzeByYear(): int
    {
        //подготавливаем накопители
        $count = 0;
        $totalCount = 0;

        //накапливаем статистику
        foreach ($this->records as $record) {
            $totalCount++;
            if ($record->getPresence()) {
                $count++;
            }
        }

        //вычисляем процент
        return (int)round(100 * $count / $totalCount);
    }

    protected function analyzeByMonth(): array
    {
        //подготавливаем накопители
        $result = array_combine(range(1, 12), array_fill(0, 12, 0));
        $counter = $result;

        //накапливаем статистику
        foreach ($this->records as $record) {
            $month = (int)$record->getDate()->format('m');
            $counter[$month]++;
            if ($record->getPresence()) {
                $result[$month]++;
            }
        }

        //вычисляем процент
        foreach ($result as $month => $count) {
            $totalCount = $counter[$month];
            $result[$month] = $totalCount ? round(100 * $count / $totalCount) : 0;
        }

        return $result;
    }

    protected function analyzeByGroup(): array
    {
        //подготавливаем накопители
        $result = [];
        foreach ($this->groups as $group) {
            $result[$group->getId()] = 0;
        }
        $counter = $result;

        //накапливаем статистику
        foreach ($this->records as $record) {
            $group = $record->getChild()->getKindGroup();
            if ($group) {
                $groupId = $group->getId();
                $counter[$groupId]++;
                if ($record->getPresence()) {
                    $result[$groupId]++;
                }
            }
        }

        //вычисляем процент
        foreach ($result as $groupId => $count) {
            $totalCount = $counter[$groupId];
            $result[$groupId] = round(100 * $count / $totalCount);
        }

        return $result;
    }

    protected function analyzeByChild(): array
    {
        //подготавливаем накопители
        $result = [];
        foreach ($this->childs as $child) {
            $result[$child->getId()] = 0;
        }
        $counter = $result;

        //накапливаем статистику
        foreach ($this->records as $record) {
            $childId = $record->getChild()->getId();
            $counter[$childId]++;
            if ($record->getPresence()) {
                $result[$childId]++;
            }
        }

        //вычисляем процент
        foreach ($result as $childId => $count) {
            $totalCount = $counter[$childId];
            $result[$childId] = round(100 * $count / $totalCount);
        }

        return $result;
    }

    protected function getTargetEmails(): array
    {
        /** @var Manager[] $managers */
        $managers = $this->em->getRepository(Manager::class)->findAll();

        //собираем почтовые ящики тех, кому нужно отправить отчет
        $targetEmails = [];
        //обязательно отправляем отчет заведующим
        foreach ($managers as $manager) {
            $targetEmails[] = $manager->getUser()->getEmail();
        }

        return array_unique($targetEmails);
    }

    protected function getEmailReport(string $reportUrl): string
    {
        $analizedData = $this->analyzedData;
        $text = 'Статистика посещаемости.' . PHP_EOL;
        $text .= PHP_EOL;
        $text .= 'Годовая посещаемость: ' . $analizedData['year'] . '%' . PHP_EOL;
        $text .= PHP_EOL;
        $text .= 'Посещаемость по месяцам:' . PHP_EOL;
        foreach ($analizedData['monthy'] as $month => $val) {
            $text .= self::months[$month] . ': ' . $val . '%' . PHP_EOL;
        }
        $text .= PHP_EOL;
        $text .= 'Посещаемость по группам:' . PHP_EOL;
        $groups = [];
        foreach ($this->groups as $group) {
            $groups[$group->getId()] = $group->getName();
        }
        foreach ($analizedData['group'] as $groupId => $val) {
            $text .= $groups[$groupId] . ': ' . $val . '%' . PHP_EOL;
        }
        $text .= PHP_EOL;
        $text .= 'Полный отчет, URL: ' . $reportUrl . PHP_EOL;

        return $text;
    }

    protected function printReport(): void
    {
        $analizedData = $this->analyzedData;
        $this->io->text('==============================');
        $this->io->text('Годовая посещаемость: ' . $analizedData['year'] . '%');
        $this->io->text('Посещаемость по месяцам:');
        foreach ($analizedData['monthy'] as $month => $val) {
            $this->io->text(self::months[$month] . ': ' . $val . '%');
        }
        $this->io->text('Посещаемость по группам:');
        $groups = [];
        foreach ($this->groups as $group) {
            $groups[$group->getId()] = $group->getName();
        }
        foreach ($analizedData['group'] as $groupId => $val) {
            $this->io->text($groups[$groupId] . ': ' . $val . '%');
        }
        $this->io->text('==============================');
    }

    protected function generateReport(): Spreadsheet
    {
        $analyzedData = $this->analyzedData;
        $groups = [];
        foreach ($this->groups as $group) {
            $groups[$group->getId()] = $group->getName();
        }
        $childs = [];
        foreach ($this->childs as $child) {
            $childs[$child->getId()] = $child;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $setTitle = function ($row, array $title) use ($sheet) {
            foreach ($title as $column => $name) {
                $cord = $column . $row;
                $sheet->setCellValue($cord, $name);
                $sheet->getStyle($cord)->getFont()->setBold(true);
                $sheet->getStyle($cord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($cord)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
            }
        };
        $setValue = function ($column, $row, $value) use ($sheet) {
            $sheet->setCellValue($column . $row, $value);
        };
        /**
         * Окрашивает ячейку в цвета от красного до зеленого, всего 100 градаций
         * @param string $column
         * @param int $color - 0 для красного, 100 для зеленого
         */
        $setColor = function (string $column, $row, int $color) use ($sheet) {
            $arColor = [
                'r' => 0,
                'g' => 0,
                'b' => 153,
            ];
            //два цвета, 255-155=100 для каждого, итого 200 оттенков на 100 градаций, по 2 оттенка на градацию
            $k = 2;
            //всего 100 градаций, 50 - середина диапазона
            $m = 50;
            //ограничиваем входной диапазон промежутком 0..100
            $color = match (true) {
                $color > 100 => 100,
                $color < 0 => 0,
                default => $color,
            };
            //вычисляем долю красного, для 0 - 255, для 50 - 255, для 100 - 155
            $arColor['r'] = match (true) {
                $color > $m => 255 - $k * ($color - $m),
                default => 255,
            };
            //вычисляем долю зеленого, для 0 - 155, для 50 - 255, для 100 - 255
            $arColor['g'] = match (true) {
                $color < $m => 255 - $k * ($m - $color),
                default => 255,
            };
            //переводим в шестнадцатеричную систему
            $strColor = array_reduce($arColor, function ($carry, $item) {
                return $carry . dechex((int)$item);
            }, '');
            //генерируем цвет
            $sheet
                ->getStyle($column . $row)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB($strColor);
        };
        $setPercentColor = function (string $column, $row, int $percent) use ($setColor) {
            $percent = match (true) {
                $percent < self::minimalPercent => self::minimalPercent,
                $percent > 100 => 100,
                default => $percent,
            };
            $color = (int)(($percent - self::minimalPercent) * (100 / (100 - self::minimalPercent)));

            $setColor($column, $row, $color);
        };

        $row = 1;
        $setTitle($row++, ['A' => 'Год', 'B' => 'Процент']);
        $setValue('A', $row, $this->time->format('Y'));
        $setValue('B', $row, $analyzedData['year'] . '%');
        $setPercentColor('B', $row, (int)$analyzedData['year']);

        $row = $sheet->getHighestDataRow() + 2;
        $setTitle($row++, ['A' => 'Месяц', 'B' => 'Процент']);
        foreach ($analyzedData['monthy'] as $month => $val) {
            $setValue('A', $row, self::months[$month]);
            $setValue('B', $row, $val . '%');
            $setPercentColor('B', $row, (int)$val);
            $row++;
        }

        $row = $sheet->getHighestDataRow() + 2;
        $setTitle($row++, ['A' => 'Группа', 'B' => 'Процент']);
        foreach ($analyzedData['group'] as $groupId => $val) {
            $setValue('A', $row, $groups[$groupId]);
            $setValue('B', $row, $val . '%');
            $setPercentColor('B', $row, (int)$val);
            $row++;
        }

        $row = $sheet->getHighestDataRow() + 2;
        $setTitle($row++, ['A' => 'Группа', 'B' => 'Фамилия', 'C' => 'Имя', 'D' => 'Процент']);
        foreach ($analyzedData['child'] as $childId => $val) {
            $child = $childs[$childId];
            $setValue('A', $row, $child->getKindGroup() ? $child->getKindGroup()->getName() : '');
            $setValue('B', $row, $child->getLastName());
            $setValue('C', $row, $child->getFirstName());
            $setValue('D', $row, $val . '%');
            $setPercentColor('D', $row, (int)$val);
            $row++;
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

        if (!is_dir($reportDir) && !mkdir($reportDir) && !is_dir($reportDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $reportDir));
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

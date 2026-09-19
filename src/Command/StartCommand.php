<?php

/*
 * This file is part of the "Remote Console" for Kimai.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiConsole\Command;

use Swagger\Client\ApiException;
use Swagger\Client\Model\TimesheetEditForm;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class StartCommand extends BaseCommand
{
    use TimesheetCommandTrait;

    protected function configure(): void
    {
        $this
            ->setName('start')
            ->setDescription('Starts a new timesheet')
            ->setHelp('This command lets you start a new timesheet and optionally end it immediately.')
            ->addOption('customer', 'c', InputOption::VALUE_OPTIONAL, 'The customer to filter the project list, can be an ID or a search term or empty (you will be prompted for a customer).')
            ->addOption('project', 'p', InputOption::VALUE_OPTIONAL, 'The project to use, can be an ID or a search term or empty. You will be prompted for the project.')
            ->addOption('activity', 'a', InputOption::VALUE_OPTIONAL, 'The activity ID to use')
            ->addOption('tags', 't', InputOption::VALUE_OPTIONAL, 'Comma separated list of tag names')
            ->addOption('description', 'd', InputOption::VALUE_OPTIONAL, 'The timesheet description')
            ->addOption('begin', 'b', InputOption::VALUE_OPTIONAL, 'The begin (date and) time in a format supported by PHP')
            ->addOption('end', 'e', InputOption::VALUE_OPTIONAL, 'The end (date and) time in a format supported by PHP')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $customer = null;

        $customerId = $input->getOption('customer');
        $projectId = $input->getOption('project');
        $activityId = $input->getOption('activity');

        if (null === $projectId) {
            $customer = $this->findCustomer($input, $output, $io, $customerId);
        }

        $project = $this->findProject($input, $output, $io, $projectId, $customer);
        if (null === $project) {
            $io->error('Cannot start timesheet: missing project');

            return 1;
        }

        if (null === $customer) {
            $customer = $this->loadCustomerById($io, $project->getCustomerId());
        }

        $activity = $this->findActivity($input, $output, $io, $activityId, $project);
        if (null === $activity) {
            $io->error('Cannot start timesheet: missing activity');

            return 1;
        }

        $api = $this->getTimesheetApi();
        $form = new TimesheetEditForm();
        $form->setProject($project->getId());
        $form->setActivity($activity->getId());

        // TODO ask for tags if given empty
        if (null !== ($tags = $input->getOption('tags'))) {
            $form->setTags($tags);
        }

        // TODO ask for description if given empty
        if (null !== ($description = $input->getOption('description'))) {
            $form->setDescription($description);
        }

        if (null !== ($begin = $input->getOption('begin'))) {
            $begin = $this->parseAndRefineDateTime($io, (string) $begin, 'begin');
            $form->setBegin($begin);
        }

        if (null !== ($end = $input->getOption('end'))) {
            $end = $this->parseAndRefineDateTime($io, (string) $end, 'end');
            $form->setEnd($end);
        }

        try {
            $timesheet = $api->postPostTimesheet($form);
        } catch (ApiException $ex) {
            $this->renderApiException($input, $io, $ex, 'Failed creating timesheet');

            return 1;
        }

        $fields = [
            'ID' => $timesheet->getId(),
            'Begin' => $timesheet->getBegin()->format(\DateTime::ISO8601),
            'End' => $timesheet->getEnd()?->format(\DateTime::ISO8601),
            'Description' => $timesheet->getDescription(),
            'Tags' => implode(PHP_EOL, $timesheet->getTags()),
            'Customer' => $customer->getName(),
            'Project' => $project->getName(),
            'Activity' => $activity->getName(),
        ];

        $io->success('Started timesheet');
        $io->horizontalTable(array_keys($fields), [$fields]);

        return 0;
    }
}

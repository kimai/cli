<?php

/*
 * This file is part of the "Remote Console" for Kimai.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiConsole\Command;

use KimaiConsole\Entity\Activity;
use KimaiConsole\Entity\Customer;
use KimaiConsole\Entity\Project;
use Swagger\Client\Api\ActivityApi;
use Swagger\Client\Api\CustomerApi;
use Swagger\Client\Api\ProjectApi;
use Swagger\Client\Model\ActivityCollection;
use Swagger\Client\Model\CustomerCollection;
use Swagger\Client\Model\ProjectCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait TimesheetCommandTrait
{
    abstract protected function getCustomerApi(): CustomerApi;

    abstract protected function getProjectApi(): ProjectApi;

    abstract protected function getActivityApi(): ActivityApi;

    private function findCustomer(InputInterface $input, OutputInterface $output, SymfonyStyle $io, string|int|null $customerId): ?Customer
    {
        $api = $this->getCustomerApi();
        $customer = null;

        if (null !== $customerId) {
            if (is_numeric($customerId)) {
                $customerId = \intval($customerId);
                $customer = $this->loadCustomerById($io, $customerId);
            } else {
                $customerList = $api->getGetCustomers('1', null, null, $customerId);
                if (\count($customerList) === 1) {
                    $customer = Customer::fromCollection($customerList[0]);
                } elseif (\count($customerList) > 1) {
                    $customer = $this->askForCustomer($io, $customerList);
                } else {
                    $io->warning(\sprintf('Could not find customer with term: %s', $customerId));
                }
            }
        }

        if (null !== $customer) {
            return $customer;
        }

        $customerList = $api->getGetCustomers('1');

        if (\count($customerList) === 0) {
            $io->error('Could not find any customer');

            return null;
        }

        return $this->askForCustomer($io, $customerList);
    }

    private function loadCustomerById(SymfonyStyle $io, string|int $id): ?Customer
    {
        $api = $this->getCustomerApi();

        try {
            $customerEntity = $api->getGetCustomer((string) $id);

            return Customer::fromEntity($customerEntity);
        } catch (\Exception $ex) {
            if ($ex->getCode() === 404) {
                $io->error(\sprintf('Customer with ID %s does not exist', $id));
            } else {
                $io->error(\sprintf('Failed loading customer with ID %s: %s', $id, $ex->getMessage()));
            }
        }

        return null;
    }

    /**
     * @param SymfonyStyle $io
     * @param array<CustomerCollection> $customers
     * @return Customer
     */
    private function askForCustomer(SymfonyStyle $io, array $customers): ?Customer
    {
        $choices = [];
        foreach ($customers as $customerEntity) {
            $choices[$customerEntity->getId()] = $customerEntity->getName();
        }

        $id = $io->choice('Please select a customer', $choices);

        $flipped = array_flip($choices);
        $id = \intval($flipped[$id]);

        foreach ($customers as $customerEntity) {
            if ($customerEntity->getId() !== $id) {
                continue;
            }

            return Customer::fromCollection($customerEntity);
        }

        $io->error('Failed loading customer with ID ' . $id);

        return null;
    }

    // ==============================================================================================================

    private function findProject(InputInterface $input, OutputInterface $output, SymfonyStyle $io, string|int|null $projectId, ?Customer $customer = null): ?Project
    {
        $api = $this->getProjectApi();
        $project = null;
        $customerId = null !== $customer ? (string) $customer->getId() : null;

        if (null !== $projectId) {
            if (is_numeric($projectId)) {
                $projectId = \intval($projectId);
                $project = $this->loadProjectById($io, $projectId);
            } else {
                $projectList = $api->getGetProjects(
                    $customerId,
                    null,  // @phpstan-ignore argument.type
                    '1',
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    $projectId
                );
                if (\count($projectList) === 1) {
                    $project = Project::fromCollection($projectList[0]);
                } elseif (\count($projectList) > 1) {
                    $project = $this->askForProject($io, $projectList);
                } else {
                    $io->warning(\sprintf('Could not find project with term: %s', $projectId));
                }
            }
        }

        if (null !== $project) {
            return $project;
        }

        $projectList = $api->getGetProjects(
            $customerId,
            null,  // @phpstan-ignore argument.type
            '1'
        );

        if (\count($projectList) === 0) {
            $io->error('Could not find any project');

            return null;
        }

        return $this->askForProject($io, $projectList);
    }

    private function loadProjectById(SymfonyStyle $io, int $id): ?Project
    {
        $api = $this->getProjectApi();

        try {
            $projectEntity = $api->getGetProject((string) $id);

            return Project::fromEntity($projectEntity);
        } catch (\Exception $ex) {
            if ($ex->getCode() === 404) {
                $io->error(\sprintf('Project with ID %s does not exist', $id));
            } else {
                $io->error(\sprintf('Failed loading project with ID %s: %s', $id, $ex->getMessage()));
            }
        }

        return null;
    }

    /**
     * @param SymfonyStyle $io
     * @param array<ProjectCollection> $projects
     * @return Project
     */
    private function askForProject(SymfonyStyle $io, array $projects): ?Project
    {
        $choices = [];
        /** @var ProjectCollection $projectEntity */
        foreach ($projects as $projectEntity) {
            $choices[$projectEntity->getId()] = $projectEntity->getName();
        }

        $id = $io->choice('Please select a project', $choices);

        $flipped = array_flip($choices);
        $id = \intval($flipped[$id]);

        /** @var ProjectCollection $projectEntity */
        foreach ($projects as $projectEntity) {
            if ($projectEntity->getId() !== $id) {
                continue;
            }

            return Project::fromCollection($projectEntity);
        }

        $io->error('Failed loading project with ID ' . $id);

        return null;
    }

    // ==============================================================================================================

    private function findActivity(InputInterface $input, OutputInterface $output, SymfonyStyle $io, string|int|null $activityId, ?Project $projectEntity = null): ?Activity
    {
        $api = $this->getActivityApi();
        $activity = null;
        $projectId = null !== $projectEntity ? (string) $projectEntity->getId() : null;

        if (null !== $activityId) {
            if (is_numeric($activityId)) {
                $activityId = \intval($activityId);
                $activity = $this->loadActivityById($io, $activityId);
            } else {
                $activityList = $api->getGetActivities(
                    $projectId,
                    null,  // @phpstan-ignore argument.type
                    '1',
                    null,
                    null,
                    null,
                    $activityId
                );
                if (\count($activityList) === 1) {
                    $activity = Activity::fromCollection($activityList[0]);
                } elseif (\count($activityList) > 1) {
                    $activity = $this->askForActivity($io, $activityList);
                } else {
                    $io->warning(\sprintf('Could not find activity with term: %s', $activityId));
                }
            }
        }

        if (null !== $activity) {
            return $activity;
        }

        $activityList = $api->getGetActivities(
            $projectId,
            null,  // @phpstan-ignore argument.type
            '1'
        );

        if (\count($activityList) === 0) {
            $io->error('Could not find any activity');

            return null;
        }

        return $this->askForActivity($io, $activityList);
    }

    private function loadActivityById(SymfonyStyle $io, int $id): ?Activity
    {
        $api = $this->getActivityApi();

        try {
            $activityEntity = $api->getGetActivity((string) $id);

            return Activity::fromEntity($activityEntity);
        } catch (\Exception $ex) {
            if ($ex->getCode() === 404) {
                $io->error(\sprintf('Activity with ID %s does not exist', $id));
            } else {
                $io->error(\sprintf('Failed loading activity with ID %s: %s', $id, $ex->getMessage()));
            }
        }

        return null;
    }

    /**
     * @param SymfonyStyle $io
     * @param array<ActivityCollection> $activities
     * @return Activity
     */
    private function askForActivity(SymfonyStyle $io, array $activities): ?Activity
    {
        $choices = [];
        /** @var ActivityCollection $activityEntity */
        foreach ($activities as $activityEntity) {
            $choices[$activityEntity->getId()] = $activityEntity->getName();
        }

        $id = $io->choice('Please select an activity', $choices);

        $flipped = array_flip($choices);
        $id = \intval($flipped[$id]);

        /* @var ActivityCollection $activity */
        foreach ($activities as $activityEntity) {
            if ($activityEntity->getId() !== $id) {
                continue;
            }

            return Activity::fromCollection($activityEntity);
        }

        $io->error('Failed loading activity with ID ' . $id);

        return null;
    }

    /**
     * Try to convert the given string into a DateTime object and recursively ask for clarification if necessary.
     */
    private function parseAndRefineDateTime(SymfonyStyle $io, string $begin, string $valueName): \DateTime
    {
        try {
            return new \DateTime($begin);
        } catch (\Exception $e) {
        }

        return $this->parseAndRefineDateTime(
            $io,
            $io->ask(\sprintf('Value "%s" for field "%s" is not a valid DateTime. Please refine:', $begin, $valueName), $begin),
            $valueName,
        );
    }
}

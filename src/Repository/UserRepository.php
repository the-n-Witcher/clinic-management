<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @implements PasswordUpgraderInterface<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->save($user, true);
    }

    public function loadUserByIdentifier(string $identifier): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->andWhere('u.isActive = true')
            ->setParameter('email', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function loadUserByUsername(string $username): ?User
    {
        return $this->loadUserByIdentifier($username);
    }

    public function findByFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC');

        if (!empty($filters['email'])) {
            $qb->andWhere('u.email LIKE :email')
                ->setParameter('email', '%' . $filters['email'] . '%');
        }

        if (!empty($filters['firstName'])) {
            $qb->andWhere('u.firstName LIKE :firstName')
                ->setParameter('firstName', '%' . $filters['firstName'] . '%');
        }

        if (!empty($filters['lastName'])) {
            $qb->andWhere('u.lastName LIKE :lastName')
                ->setParameter('lastName', '%' . $filters['lastName'] . '%');
        }

        if (!empty($filters['role'])) {
            $qb->andWhere('JSON_CONTAINS(u.roles, :role) = 1')
                ->setParameter('role', json_encode($filters['role']));
        }

        if (isset($filters['isActive'])) {
            $qb->andWhere('u.isActive = :isActive')
                ->setParameter('isActive', $filters['isActive']);
        }

        if (!empty($filters['createdFrom'])) {
            $createdFrom = \DateTime::createFromFormat('Y-m-d', $filters['createdFrom']);
            if ($createdFrom) {
                $createdFrom->setTime(0, 0, 0);
                $qb->andWhere('u.createdAt >= :createdFrom')
                    ->setParameter('createdFrom', $createdFrom);
            }
        }

        if (!empty($filters['createdTo'])) {
            $createdTo = \DateTime::createFromFormat('Y-m-d', $filters['createdTo']);
            if ($createdTo) {
                $createdTo->setTime(23, 59, 59);
                $qb->andWhere('u.createdAt <= :createdTo')
                    ->setParameter('createdTo', $createdTo);
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function search(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.email LIKE :query')
            ->orWhere('u.firstName LIKE :query')
            ->orWhere('u.lastName LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findUsersByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->andWhere('u.isActive = true')
            ->setParameter('role', json_encode($role))
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findInactiveUsers(\DateTimeInterface $cutoffDate): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.lastLogin < :cutoffDate OR u.lastLogin IS NULL')
            ->andWhere('u.isActive = true')
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('u.lastLogin', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getStatistics(): array
    {
        $total = $this->count([]);

        $active = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();

        $inactive = $total - $active;

        $rolesStats = $this->createQueryBuilder('u')
            ->select('u.roles, COUNT(u.id) as count')
            ->groupBy('u.roles')
            ->getQuery()
            ->getResult();

        $formattedRolesStats = [];
        foreach ($rolesStats as $stat) {
            $roles = json_decode($stat['roles'], true);
            $mainRole = $roles[0] ?? 'ROLE_USER';
            if (!isset($formattedRolesStats[$mainRole])) {
                $formattedRolesStats[$mainRole] = 0;
            }
            $formattedRolesStats[$mainRole] += $stat['count'];
        }

        $lastMonth = new \DateTime();
        $lastMonth->modify('-1 month');

        $newLastMonth = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :lastMonth')
            ->setParameter('lastMonth', $lastMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => (int) $total,
            'active' => (int) $active,
            'inactive' => (int) $inactive,
            'by_role' => $formattedRolesStats,
            'new_last_month' => (int) $newLastMonth,
            'active_percentage' => $total > 0 ? round(($active / $total) * 100, 2) : 0
        ];
    }

    public function findUsersWithDoctorProfile(): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.doctor', 'd')
            ->addSelect('d')
            ->where('u.isActive = true')
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findUsersWithPatientProfile(): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.patient', 'p')
            ->addSelect('p')
            ->where('u.isActive = true')
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findUsersWithoutProfile(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.doctor IS NULL')
            ->andWhere('u.patient IS NULL')
            ->andWhere('u.isActive = true')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function updateLastLogin(User $user): void
    {
        $user->setLastLogin(new \DateTime());
        $this->save($user, true);
    }

    public function findRecentlyLoggedInUsers(int $days = 7): array
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify("-$days days");

        return $this->createQueryBuilder('u')
            ->where('u.lastLogin >= :cutoffDate')
            ->andWhere('u.isActive = true')
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('u.lastLogin', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function deactivateInactiveUsers(\DateTimeInterface $cutoffDate): int
    {
        $qb = $this->createQueryBuilder('u')
            ->update()
            ->set('u.isActive', 'false')
            ->where('u.lastLogin < :cutoffDate OR u.lastLogin IS NULL')
            ->andWhere('u.isActive = true')
            ->andWhere('u.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate);

        return $qb->getQuery()->execute();
    }

    public function findDuplicateEmails(): array
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('email', 'email');
        $rsm->addScalarResult('count', 'count');

        $sql = "
            SELECT email, COUNT(*) as count
            FROM users
            GROUP BY email
            HAVING COUNT(*) > 1
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        
        return $query->getResult();
    }

    public function getUserActivityReport(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('date', 'date');
        $rsm->addScalarResult('new_users', 'new_users');
        $rsm->addScalarResult('active_users', 'active_users');

        $sql = "
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as new_users,
                (SELECT COUNT(DISTINCT u2.id) 
                 FROM users u2 
                 WHERE DATE(u2.last_login) = DATE(u1.created_at)
                   AND u2.is_active = true) as active_users
            FROM users u1
            WHERE created_at BETWEEN :startDate AND :endDate
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('startDate', $startDate);
        $query->setParameter('endDate', $endDate);
        
        return $query->getResult();
    }

    public function findUsersByMultipleRoles(array $roles): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.isActive = true');

        $orX = $qb->expr()->orX();
        foreach ($roles as $role) {
            $orX->add($qb->expr()->eq('JSON_CONTAINS(u.roles, :role_' . $role . ')', '1'));
            $qb->setParameter('role_' . $role, json_encode($role));
        }

        $qb->andWhere($orX)
            ->orderBy('u.lastName', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function resetFailedLogins(): int
    {
        // $qb = $this->createQueryBuilder('u')
        //     ->update()
        //     ->set('u.failedLoginAttempts', '0')
        //     ->where('u.failedLoginAttempts > 0');
        
        // return $qb->getQuery()->execute();

        return 0;
    }

    public function findUsersCreatedBetween(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.createdAt BETWEEN :startDate AND :endDate')
            ->andWhere('u.isActive = true')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getUsersCountByMonth(int $year): array
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('month', 'month');
        $rsm->addScalarResult('count', 'count');

        $sql = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count
            FROM users
            WHERE YEAR(created_at) = :year
                AND is_active = true
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('year', $year);
        
        return $query->getResult();
    }

    public function findAdminUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->andWhere('u.isActive = true')
            ->setParameter('role', json_encode('ROLE_ADMIN'))
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDoctorUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.doctor', 'd')
            ->addSelect('d')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->andWhere('u.isActive = true')
            ->setParameter('role', json_encode('ROLE_DOCTOR'))
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findReceptionistUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->andWhere('u.isActive = true')
            ->setParameter('role', json_encode('ROLE_RECEPTIONIST'))
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPatientUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.patient', 'p')
            ->addSelect('p')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->andWhere('u.isActive = true')
            ->setParameter('role', json_encode('ROLE_PATIENT'))
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getUserGrowthTrend(int $months = 12): array
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('month', 'month');
        $rsm->addScalarResult('total_users', 'total_users');
        $rsm->addScalarResult('new_users', 'new_users');

        $sql = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as total_users,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 ELSE 0 END) as new_users
            FROM users
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :months MONTH)
                AND is_active = true
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('months', $months);
        
        return $query->getResult();
    }

    public function findUsersWithExpiringPassword(\DateTimeInterface $expiryDate): array
    {
        // return $this->createQueryBuilder('u')
        //     ->where('u.passwordChangedAt < :expiryDate OR u.passwordChangedAt IS NULL')
        //     ->andWhere('u.isActive = true')
        //     ->setParameter('expiryDate', $expiryDate)
        //     ->getQuery()
        //     ->getResult();

        return [];
    }

    public function updateUserRoles(User $user, array $newRoles): void
    {
        $user->setRoles($newRoles);
        $this->save($user, true);
    }

    public function toggleUserActiveStatus(User $user): bool
    {
        $newStatus = !$user->isActive();
        $user->setIsActive($newStatus);
        $this->save($user, true);
        
        return $newStatus;
    }

    public function bulkDeactivateUsers(array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }

        $qb = $this->createQueryBuilder('u')
            ->update()
            ->set('u.isActive', 'false')
            ->where('u.id IN (:ids)')
            ->andWhere('u.isActive = true')
            ->setParameter('ids', $userIds);

        return $qb->getQuery()->execute();
    }

    public function bulkActivateUsers(array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }

        $qb = $this->createQueryBuilder('u')
            ->update()
            ->set('u.isActive', 'true')
            ->where('u.id IN (:ids)')
            ->andWhere('u.isActive = false')
            ->setParameter('ids', $userIds);

        return $qb->getQuery()->execute();
    }
}
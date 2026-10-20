<?php

declare(strict_types=1);
require_once dirname(__DIR__).'/src/PushService.php';

final class ClaimStatement extends PDOStatement
{
    public function __construct(private bool $listing) {}
    public function execute(?array $params = null): bool { return true; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return [['id'=>1]]; }
    public function rowCount(): int { return 0; } // Another worker won this claim.
}
final class ClaimDatabase extends PDO
{
    public int $queries=0;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries++;
        if(str_starts_with($query,'SELECT * FROM push_queue'))return new ClaimStatement(true);
        if(str_starts_with($query,'UPDATE push_queue SET status=\'processing\''))return new ClaimStatement(false);
        throw new RuntimeException('Losing worker must not inspect recipients or deliver.');
    }
}
// Dummy configuration only; no key generation and no outbound connection.
putenv('PUSH_VAPID_PUBLIC_KEY=test');
putenv('PUSH_VAPID_PRIVATE_KEY_B64='.base64_encode('test'));
$db=new ClaimDatabase();
$result=PushService::processQueue($db);
if($db->queries!==2||$result!==['sent'=>0,'failed'=>0])throw new RuntimeException('Losing claim was processed.');
echo "PASS: losing push claim performs no delivery (stubbed database).\n";

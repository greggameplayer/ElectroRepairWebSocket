<?php
namespace Chat;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {
    protected $clients;
    private $subscriptions;
    private $users;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->subscriptions = [];
        $this->users = [];

        echo "Server Started\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection to send messages to later

        $this->clients->attach($conn);

        $this->users[$conn->resourceId] = $conn;

        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $numRecv = count($this->clients) - 1;
        echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
            , $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's');
        $data = json_decode($msg, true);
        switch ($data['command']) {
            case "subscribe":
                $this->subscriptions[$from->resourceId] = [
                    "channel" => $data['channel'],
                    "realUserId" => $data['realUserId']
                ];
                break;
            case "message":
                $data['from'] = $data['youUserId'];
                $data['to'] = $data['othUserId'];
                $data['dt'] = date("d/m/Y H:i:s");

                if (isset($this->subscriptions[$from->resourceId]['channel'])) {
                    $target = $this->subscriptions[$from->resourceId]['channel'];
                    foreach ($this->subscriptions as $id=>$val) {
                        if ($val['channel'] == $target && $id != $from->resourceId) {
                            $this->users[$id]->send(json_encode($data));
                        }
                    }
                }
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);
        $qSetPresence = \Helpers\getDatabaseConnection()->prepare("UPDATE users SET ChatPresence = :state WHERE IDuser = :id");
        $qSetPresence->execute([
           "state" => 0,
            "id" => $this->subscriptions[$conn->resourceId]['realUserId']
        ]);
        $qSetPresence->closeCursor();
        unset($this->users[$conn->resourceId]);
        unset($this->subscriptions[$conn->resourceId]);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";

        $qSetPresence = \Helpers\getDatabaseConnection()->prepare("UPDATE users SET ChatPresence = :state WHERE IDuser = :id");
        $qSetPresence->execute([
            "state" => 0,
            "id" => $this->subscriptions[$conn->resourceId]['realUserId']
        ]);
        $qSetPresence->closeCursor();

        $conn->close();
    }
}

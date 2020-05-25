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
                $this->subscriptions[$from->resourceId] = $data['channel'];
                break;
            case "message":
                $user = \Models\getUserById($data['youUserId']);
                $oth = \Models\getUserById($data['othUserId']);
                $data['from'] = $user[0]['Prenom'] . ' ' . $user[0]['Nom'];
                $data['to'] = $oth[0]['Prenom'] . ' ' . $oth[0]['Nom'];
                $data['dt'] = date("d/m/Y H:i:s");

                if (isset($this->subscriptions[$from->resourceId])) {
                    $target = $this->subscriptions[$from->resourceId];
                    foreach ($this->subscriptions as $id=>$channel) {
                        if ($channel == $target && $id != $from->resourceId) {
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
        unset($this->users[$conn->resourceId]);
        unset($this->subscriptions[$conn->resourceId]);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }
}

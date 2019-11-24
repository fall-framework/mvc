<?php

namespace fall\mvc\web\server;

use fall\core\lang\EventFlowTrait;
use fall\core\lang\stream\impl\SocketStream;
use fall\core\net\InetSocketAddress;
use fall\core\net\ServerSocket;
use fall\core\net\Socket;

/**
 * This is the base class for handling socket connection
 * @author Angelis <angelis@users.noreply.github.com>
 */
abstract class AbstractServer
{
  use EventFlowTrait;

  const SERVER_START_EVENT = "server.start";
  const SERVER_CLOSE_EVENT = "server.close";
  const SOCKET_CONNECT_EVENT = "socket.connect";
  const SOCKET_DISCONNECT_EVENT = "socket.disconnect";
  const SOCKET_MESSAGE_EVENT = "socket.message";

  private $serverSocket;
  private $sockets = [];

  public function __construct($port)
  {
    $this->serverSocket = (new ServerSocket(InetSocketAddress::createUnresolved('0.0.0.0', $port), 10));
  }

  public function start()
  {
    $this->trigger('server.start');
    while (true) {
      $readSockets = array_map(function ($element) {
        return $element->getSocket();
      }, $this->sockets);
      array_unshift($readSockets, $this->serverSocket->getSocketResource());

      $this->serverSocket->select($readSockets);
      foreach ($this->sockets as $socket) {
        if (in_array($socket->getSocket(), $readSockets)) {
          $socketStream = new SocketStream($socket);
          $this->trigger(self::SOCKET_MESSAGE_EVENT, array($socket, $socketStream));
        }
      }

      if (in_array($this->serverSocket->getSocketResource(), $readSockets)) {
        $socket = $this->serverSocket->accept();
        if ($socket !== null) {
          $this->addSocket($socket);
        }
      }
    }

    $this->serverSocket->close();
    $this->trigger(self::SERVER_CLOSE_EVENT);
  }

  public function addOnSocketConnectCallback($callback): AbstractServer
  {
    $this->on(self::SOCKET_CONNECT_EVENT, $callback);
    return $this;
  }

  public function addOnSocketMessageCallback($callback): AbstractServer
  {
    $this->on(self::SOCKET_MESSAGE_EVENT, $callback);
    return $this;
  }

  public function addOnServerStartCallback($callback): AbstractServer
  {
    $this->on(self::SERVER_START_EVENT, $callback);
    return $this;
  }

  public function addOnServerCloseCallback($callback): AbstractServer
  {
    $this->on(self::SERVER_CLOSE_EVENT, $callback);
    return $this;
  }

  protected function addSocket(Socket $socket)
  {
    $this->trigger(self::SOCKET_CONNECT_EVENT, array($socket));
    $this->sockets[] = $socket;
  }

  protected function removeSocket(Socket $socket)
  {
    $index = array_search($socket, $this->sockets);
    if ($index !== FALSE) {
      $this->trigger(self::SOCKET_DISCONNECT_EVENT, array($socket));
      $socket->close();
      unset($this->sockets[$index]);
    }
  }
}

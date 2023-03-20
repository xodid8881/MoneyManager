<?php
declare(strict_types=1);

namespace MoneyManager;

use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerJoinEvent;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\permission\DefaultPermissions;


class MoneyManager extends PluginBase implements Listener {
  private static $instance = null;
  const PREFIX = "§c【 §fMoney §c】 §7: ";
  public static function getInstance() : MoneyManager{
    return self::$instance;
  }
  protected function onLoad() : void{
    self::$instance = $this;
  }


  public function onEnable():void
  {
    @mkdir ( $this->getDataFolder () );
    $this->money = new Config ($this->getDataFolder() . "moneys.yml", Config::YAML);
    $this->moneydb = $this->money->getAll();
    $this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
  }

  public function OnJoin (PlayerJoinEvent $event)
  {
    $player = $event->getPlayer ();
    $name = $player->getName ();
    if (!isset($this->moneydb ["돈"] [strtolower($name)])){
      $this->moneydb ["돈"] [strtolower($name)] = 100000;
      $this->save ();
    }
  }

  public function getMaxPage()
  {
    $count = count($this->moneydb["돈"]);
    return floor($count / 5) + 1;
  }

  public function sendMoneyList(Player $player, $page): bool
  {
    if ($page < 1)
    $page = 1;
    if ($page > $this->getMaxPage())
    $page = $this->getMaxPage();
    $index1 = $page * 5 - 4;
    $index2 = $page * 5;
    arsort($this->moneydb ["돈"]);
    $count = 0;
    foreach (array_keys($this->moneydb ["돈"]) as $name) {
      $count ++;
      $money = $this->moneydb ["돈"] [$name];
      if ($index1 <= $count and $index2 >= $count) {
        $player->sendMessage("§c【 {$count}위 §c】 §r§7{$name}님: {$this->getKoreanMoney($money)}");
      }
    }
    return true;
  }

  public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
  {
    $tag = "§c【 §fMoney §c】 §7: ";
    $command = $command->getName ();
    $name = $sender->getName ();
    if ($command == "돈") {
      $sender->sendMessage ( $tag . "/돈순위 [ 페이지 ] - 서버 돈 순위를 확인합니다." );
      $sender->sendMessage ( $tag . "/돈보기 [ 닉네임 ] - 플레이어의 돈을 확인합니다." );
      $sender->sendMessage ( $tag . "/돈보내기 [ 닉네임 ] [ 금액 ] - 플레이어에게 돈을 보냅니다." );
      $sender->sendMessage ( $tag . "/돈지급 [ 닉네임 ] [ 금액 ] - 권한을 사용해 플레이어에게 돈을 보냅니다." );
      $sender->sendMessage ( $tag . "/돈설정 [ 닉네임 ] [ 금액 ] - 권한을 사용해 플레이어 돈을 설정합니다." );
      return true;
    }
    if ($command == "돈순위") {
      if( ! isset($args[0])){
        $this->sendMoneyList($sender, 0);
        return true;
      } else {
        $this->sendMoneyList($sender, $args[0]);
        return true;
      }
    }
    if ($command == "돈보기") {
      if( ! isset($args[0] )){
        $sender->sendMessage ( $tag . "/돈보기 [ 닉네임 ] - 플레이어의 돈을 확인합니다." );
        return true;
      }
      if (isset($this->moneydb ["돈"] [strtolower($args[0])])) {
        $money = $this->moneydb ["돈"] [strtolower($args[0])];
        $sender->sendMessage ($tag."해당 플레이어의 돈정보 입니다.");
        $sender->sendMessage ($tag. $this->getKoreanMoney($money) . " 을 보유중입니다.");
        return true;
      } else {
        $sender->sendMessage ($tag."해당 플레이어는 존재하지 않습니다.");
        return true;
      }
    }
    if ($command == "돈보내기") {
      if( ! isset($args[0] )){
        $sender->sendMessage ( $tag . "/돈보내기 [ 닉네임 ] [ 금액 ] - 플레이어에게 돈을 보냅니다." );
        return true;
      }
      if( ! isset($args[1] )){
        $sender->sendMessage ( $tag . "/돈보내기 [ 닉네임 ] [ 금액 ] - 플레이어에게 돈을 보냅니다." );
        return true;
      }
      if (isset($this->moneydb ["돈"] [strtolower($args[0])])) {
        $MyMoney = $this->getMoney ($name);
        $KoreaMoney = $this->getKoreanMoney($args[1]);
        if ($MyMoney >= $args[1]){
          $sender->sendMessage ($tag."해당 플레이어에게 돈을 보냈습니다.");
          $sender->sendMessage ($tag."보낸 돈 금액 : " . $KoreaMoney);
          $this->addMoneyMessage ($name, $args[0], $args[1]);
          $this->moneydb ["돈"] [strtolower($name)] -= $args[1];
          $this->moneydb ["돈"] [strtolower($args[0])] += $args[1];
          $this->save ();
          return true;
        } else {
          $sender->sendMessage ($tag."당신은 해당 금액을 보낼 수 없습니다.");
          $sender->sendMessage ($tag."사유 - 돈부족");
          return true;
        }
        return true;
      } else {
        $sender->sendMessage ($tag."해당 플레이어는 존재하지 않습니다.");
        return true;
      }
    }
    if ($command == "돈지급") {
      if (!$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)) {
        $sender->sendMessage($tag."권한이 없습니다.");
        return true;
      }
      if( ! isset($args[0] )){
        $sender->sendMessage ( $tag . "/돈지급 [ 닉네임 ] [ 금액 ] - 권한을 사용해 플레이어에게 돈을 보냅니다." );
        return true;
      }
      if( ! isset($args[1] )){
        $sender->sendMessage ( $tag . "/돈지급 [ 닉네임 ] [ 금액 ] - 권한을 사용해 플레이어에게 돈을 보냅니다." );
        return true;
      }
      if (isset($this->moneydb ["돈"] [strtolower($args[0])])) {
        $MyMoney = $this->getMoney ($name);
        $KoreaMoney = $this->getKoreanMoney($args[1]);
        $sender->sendMessage ($tag."해당 플레이어에게 돈을 보냈습니다.");
        $sender->sendMessage ($tag."보낸 돈 금액 : " . $KoreaMoney);
        $this->giveMoneyMessage ($name, $args[0], $args[1]);
        $this->moneydb ["돈"] [strtolower($args[0])] += $args[1];
        $this->save ();
        return true;
      } else {
        $sender->sendMessage ($tag."해당 플레이어는 존재하지 않습니다.");
        return true;
      }
    }
    if ($command == "돈설정") {
      if (!$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)) {
        $sender->sendMessage($tag."권한이 없습니다.");
        return true;
      }
      if( ! isset($args[0] )){
        $sender->sendMessage ( $tag . "/돈설정 [ 닉네임 ] [ 금액 ] - 권한을 사용해 플레이어 돈을 설정합니다." );
        return true;
      }
      if( ! isset($args[1] )){
        $sender->sendMessage ( $tag . "/돈설정 [ 닉네임 ] [ 금액 ] - 권한을 사용해 플레이어 돈을 설정합니다." );
        return true;
      }
      if (isset($this->moneydb ["돈"] [strtolower($args[0])])) {
        $MyMoney = $this->getMoney ($name);
        $KoreaMoney = $this->getKoreanMoney($args[1]);
        $sender->sendMessage ($tag."해당 플레이어의 돈을 설정했습니다.");
        $sender->sendMessage ($tag."설정한 돈 금액 : " . $KoreaMoney);
        $this->setMoneyMessage ($name, $args[0], $args[1]);
        $this->moneydb ["돈"] [strtolower($args[0])] = $args[1];
        $this->save ();
        return true;
      } else {
        $sender->sendMessage ($tag."해당 플레이어는 존재하지 않습니다.");
        return true;
      }
    }
    return true;
  }

  public function getKoreanMoney($money) : string
  {
    $str = '';
    $elements = [];
    if($money >= 1000000000000){
      $elements[] = floor($money / 1000000000000) . "조";
      $money %= 1000000000000;
    }
    if($money >= 100000000){
      $elements[] = floor($money / 100000000) . "억";
      $money %= 100000000;
    }
    if($money >= 10000){
      $elements[] = floor($money / 10000) . "만";
      $money %= 10000;
    }
    if(count($elements) == 0 || $money > 0){
      $elements[] = $money;
    }
    return implode(" ", $elements) . "원";
  }

  public function addMoneyMessage ($PlayerName, $EventPlayerName, $money)
  {
    $tag = "§l§b[돈]§r§7 ";
    $KoreaMoney = $this->getKoreanMoney($money);
    foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
      $name = $player->getName ();
      if ($EventPlayerName == strtolower($name)){
        $player->sendMessage ($tag. $PlayerName . "님이 당신에게 돈을 보냈습니다.");
        $player->sendMessage ($tag. $PlayerName . "님에게 받은 돈 금액 : " . $KoreaMoney);
        return true;
      }
    }
  }

  public function giveMoneyMessage ($PlayerName, $EventPlayerName, $money)
  {
    $tag = "§l§b[돈]§r§7 ";
    $KoreaMoney = $this->getKoreanMoney($money);
    foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
      $name = $player->getName ();
      if ($EventPlayerName == strtolower($name)){
        $player->sendMessage ($tag. "운영진으로 부터 돈을 받았습니다.");
        $player->sendMessage ($tag. $PlayerName . "님이 당신에게 돈을 보냈습니다.");
        $player->sendMessage ($tag. $PlayerName . "님에게 받은 돈 금액 : " . $KoreaMoney);
        return true;
      }
    }
  }

  public function setMoneyMessage ($PlayerName, $EventPlayerName, $money)
  {
    $tag = "§l§b[돈]§r§7 ";
    $KoreaMoney = $this->getKoreanMoney($money);
    foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
      $name = $player->getName ();
      if ($EventPlayerName == strtolower($name)){
        $player->sendMessage ($tag. "운영진으로 부터 돈을 설정받았습니다.");
        $player->sendMessage ($tag. $PlayerName . "님이 당신의 돈을 설정했습니다.");
        $player->sendMessage ($tag. $PlayerName . "님 설정한 돈 금액 : " . $KoreaMoney);
        return true;
      }
    }
  }

  public function getMoney ($name)
  {
    if (isset($this->moneydb ["돈"] [strtolower($name)])){
      return $this->moneydb ["돈"] [strtolower($name)];
    } else {
      return 0;
    }
  }

  public function setMoney ($name, $money)
  {
    if (isset($this->moneydb ["돈"] [strtolower($name)])){
      $this->moneydb ["돈"] [strtolower($name)] = $money;
      $this->save ();
    }
  }

  public function addMoney ($name, $money)
  {
    if (isset($this->moneydb ["돈"] [strtolower($name)])){
      $this->moneydb ["돈"] [strtolower($name)] += $money;
      $this->save ();
    }
  }

  public function sellMoney ($name, $money)
  {
    if (isset($this->moneydb ["돈"] [strtolower($name)])){
      $this->moneydb ["돈"] [strtolower($name)] -= $money;
      $this->save ();
    }
  }

  public function onDisable():void
  {
    $this->save();
  }
  public function save():void
  {
    $this->money->setAll($this->moneydb);
    $this->money->save();
  }

}

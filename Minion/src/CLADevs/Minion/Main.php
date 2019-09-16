<?php

namespace CLADevs\Minion;

use CLADevs\Minion\upgrades\EventListener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as C;
use CLADevs\Minion\Minion;
class Main extends PluginBase implements Listener{

	private static $instance;

	public function onLoad(): void{
		self::$instance = $this;
	}
	public static function ok($name){
		self::$instance->getConfig()->setNested($name,"no");        
        self::$instance->getConfig()->setAll(self::$instance->getConfig()->getAll());
        self::$instance->getConfig()->save();
	}
	public function onEnable(): void{
		Entity::registerEntity(Minion::class, true);
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
	}
	
	public static function get(): self{
		return self::$instance;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
	    if($sender instanceof Player){
            //to remove cmd remove this below than go to plugin.yml remove commands section
            if(strtolower($command->getName()) === "minion"){
                if($this->getConfig()->getNested($sender->getName()) == "spawned"){
                	$sender->sendMessage("§aBạn đã spawn 1 minion, hãy thu hồi trc khi spawn");
                	return false;
                }
                
                $this->spawnde($sender);
               // $sender->getInventory()->addItem($this->getItem());
            }
        }
        return true;
    }
    public function spawnde($p){
    	$player = $p;$owner = $p->getName();
            $nbt = Entity::createBaseNBT($player, null, (90 + ($player->getDirection() * 90)) % 360);
            $nbt->setInt("Time", 3);
            if(null !== $player->namedtag->getTag("Skin")){
            $nbt->setTag($player->namedtag->getTag("Skin"));
        }
            $entity = new Minion($player->getLevel(), $nbt);
            $entity->spawnToAll();
            $this->getConfig()->setNested("$owner","spawned");        
            $this->getConfig()->setAll($this->getConfig()->getAll());
            $this->getConfig()->save();       
    }
    public function getItem(): Item{
	    $item = Item::get(Item::NETHER_STAR);
	    $item->setCustomName(C::GREEN . "Miner " . C::GOLD . "Summoner");
	    $item->setLore([C::GRAY . "Automatic Miner"]);
	    $nbt = $item->getNamedTag();
	    $nbt->setString("summon", "miner");
	    $item->setNamedTag($nbt);
	    return $item;
    }
}

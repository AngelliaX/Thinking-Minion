<?php

namespace CLADevs\Minion;

use CLADevs\Minion\upgrades\HopperInventory;
use pocketmine\block\Block;
use pocketmine\block\Chest;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\math\Vector3;
use pocketmine\timings\Timings;
use CLADevs\Minion\Main;
use onebone\economyland\EconomyLand as el;
use function yaml_parse;
class Minion extends Human{
    public $owner = "";private $lockTicker = 0;
    public function initEntity(): void{              
        parent::initEntity();
        $this->setHealth(1);
        $this->setMaxHealth(1);
        $this->setNameTagAlwaysVisible();
        $this->setScale(0.5);
        $this->sendSpawnItems();
        $this->ready();
    }
    public function ready(){
        print("ola");

        var_dump($this->getNameTag());
        $name = explode("'",$this->getNameTag())[0];
        if($name != ""){
            $this->owner = $name;
            return false;
        }
        $name = $this->getNearestPlayer()->getName();
        $this->owner = $name;
        $this->setNameTag($this->owner."'s minion");    
    }
    public function attack(EntityDamageEvent $source): void{

        $source->setCancelled();
        if($source instanceof EntityDamageByEntityEvent){
            $damager = $source->getDamager();

            if($damager instanceof Player){
                $this->ready();
                if($damager->getName() !== $this->owner){
                    $damager->sendMessage("§cYou are not the owner");
                    return;
                }
                if($damager->getInventory()->getItemInHand()->getId() !== Item::AIR){
                    $this->flagForDespawn();
                    return;
                }
                $pos = new Position(intval($damager->getX()), intval($damager->getY()) + 2, intval($damager->getZ()), $damager->getLevel());
                $damager->addWindow(new HopperInventory($pos, $this));
            }
        }
    }
    private function toFloat($num) : float{
        while(((abs($num) > 1) or (abs($num) < 0.1)) and (abs($num) > 0)) {
            if(abs($num) > 1) $num /= 10;
            if(abs($num) < 0.1) $num *= 10;
        }
        return $num;
    }
    private function getNearestPlayer() {
        $dis = 20;
        $player = false;
        foreach($this->getLevel()->getServer()->getOnlinePlayers() as $p){
            if($p->distance($this) < $dis){
                $dis = $p->distance($this);
                $player = $p;
            }
        }
        return $player;
    }
    public $banBlock = [7,54,146,410,218];
    public function canTouch($x, $z, $level, Player $player){
        $land = $this->getLevel()->getServer()->getPluginManager()->getPlugin("EconomyLand");
        $a = file_get_contents($land->getDataFolder()."Land.yml");
        $b = yaml_parse($a);
       // print("x: $x, z: $z, level : $level");
        foreach($b as $land){
            //var_dump($land);
            if($level === $land["level"] and $land["startX"] <= $x and $land["endX"] >= $x and $land["startZ"] <= $z and $land["endZ"] >= $z){
               // print("called");
                if($player->getName() === $land["owner"] or isset($land["invitee"][$player->getName()]) or $player->hasPermission("economyland.land.modify.others")){ // If owner is correct
                    return true;
                }else{ // If owner is not correct
                    return false;
                }
            }
        }
    //  return !in_array($level, $this->config["white-land"]) or $player->hasPermission("economyland.land.modify.whiteland");
        return -1; // If no land found
    }
    public function entityBaseTick(int $tickDiff = 1): bool{
        
        $player = $this->getLevel()->getServer()->getPlayer($this->owner);
        if(null == $player){
            return false;
        }
        $update = parent::entityBaseTick($tickDiff);
        $block = $this->getTargetBlock(10);
        $x = $block->getX();$z = $block->getZ();$level = $block->getLevel()->getName();
        if(in_array($block->getID(),$this->banBlock)) {return false;}
        if($block->getID() == 0){goto a;}
        if($this->getLevel()->getServer()->getTick() % $this->getMineTime() == 0){
            $land = $this->getLevel()->getServer()->getPluginManager()->getPlugin("EconomyLand");
            if(false){
            $canBreak = $this->canTouch($x, $z, $level, $player);
            if($canBreak === false){$player->sendMessage("§cYour minion is breaking someone's land");return false;}
            }        
                    $pk = new AnimatePacket();
                    $pk->entityRuntimeId = $this->id;
                    $pk->action = AnimatePacket::ACTION_SWING_ARM;
                    foreach (Server::getInstance()->getOnlinePlayers() as $p) $p->dataPacket($pk);
                    $this->breakBlock($block);
                //}
            //}
        }
        a:
        $locked = $this->getLevel()->getServer()->getPlayer($this->owner);  
            if(!$locked){
                $this->motion->x = 0;
                $this->motion->z = 0;
            }else{         
                if(++$this->lockTicker >= 10){

                    $targetDirection = new Vector3($this->toFloat($locked->x - $this->x), 0, $this->toFloat($locked->z - $this->z));              
                    $this->setRotation($locked->yaw +55,$locked->pitch);
                   // var_dump($this->distance($locked));                
                    if($this->distance($locked) >= 20){
                        $this->teleport($locked->getPosition());
                        return false;                   
                    }             
                    
                    if($this->distance($locked) < 10){            
                        if(null == $block){
                            $this->motion->x = $targetDirection->x *0.3;                    
                            $this->motion->z = $targetDirection->z *0.3; 
                        }else{
                            $this->motion->x = $block->x * 0.0009;                     
                            $this->motion->z = $block->z * 0.0009;     
                        }         
                    }else if($this->distance($locked) >= 10){
                        $this->motion->x = $targetDirection->x * 0.3;                     
                        $this->motion->z = $targetDirection->z * 0.3;              
                    }else{                
                        $this->motion->x = 0;
                        $this->motion->z = 0;
                        $this->lockTicker = 0;
                    }
                }
            }

            $friction = 1 - 0.02;
            $this->motion->y -= 0.08;
            $this->motion->y *= $friction;


            $this->move($this->motion->x,$this->motion->y, $this->motion->z);
            $this->updateMovement();

        return $update;
    }
    public function move(float $dx, float $dy, float $dz) : void {
        $this->blocksAround = null;
        Timings::$entityMoveTimer->startTiming();
        $movX = $dx;
        $movY = $dy;
        $movZ = $dz;
        if($this->keepMovement) {
            $this->boundingBox->offset($dx, $dy, $dz);
        }else {
            $this->ySize *= 0.4;
            $axisalignedbb = clone $this->boundingBox;
            $list = $this->level->getCollisionCubes($this, $this->boundingBox->addCoord($dx, $dy, $dz), false);
            foreach($list as $bb) {
                $dy = $bb->calculateYOffset($this->boundingBox, $dy);
            }
            $this->boundingBox->offset(0, $dy, 0);
            $fallingFlag = ($this->onGround or ($dy != $movY and $movY < 0));
            foreach($list as $bb) {
                $dx = $bb->calculateXOffset($this->boundingBox, $dx);
            }
            $this->boundingBox->offset($dx, 0, 0);
            foreach($list as $bb) {
                $dz = $bb->calculateZOffset($this->boundingBox, $dz);
            }
            $this->boundingBox->offset(0, 0, $dz);
            if($this->stepHeight > 0 and $fallingFlag and $this->ySize < 0.05 and ($movX != $dx or $movZ != $dz)) {
                $cx = $dx;
                $cy = $dy;
                $cz = $dz;
                $dx = $movX;
                $dy = $this->stepHeight;
                $dz = $movZ;
                $axisalignedbb1 = clone $this->boundingBox;
                $this->boundingBox->setBB($axisalignedbb);
                $list = $this->level->getCollisionCubes($this, $this->boundingBox->addCoord($dx, $dy, $dz), false);
                foreach($list as $bb) {
                    $dy = $bb->calculateYOffset($this->boundingBox, $dy);
                }
                $this->boundingBox->offset(0, $dy, 0);
                foreach($list as $bb) {
                    $dx = $bb->calculateXOffset($this->boundingBox, $dx);
                }
                $this->boundingBox->offset($dx, 0, 0);
                foreach($list as $bb) {
                    $dz = $bb->calculateZOffset($this->boundingBox, $dz);
                }
                $this->boundingBox->offset(0, 0, $dz);
                if(($cx ** 2 + $cz ** 2) >= ($dx ** 2 + $dz ** 2)) {
                    $dx = $cx;
                    $dy = $cy;
                    $dz = $cz;
                    $this->boundingBox->setBB($axisalignedbb1);
                }else {
                    $block = $this->level->getBlock($this->getSide(Vector3::SIDE_DOWN));
                    $blockBB = $block->getBoundingBox() ?? new AxisAlignedBB($block->x, $block->y, $block->z, $block->x + 1, $block->y + 1, $block->z + 1);
                    $this->ySize += $blockBB->maxY - $blockBB->minY;
                }
            }
        }
        $this->x = ($this->boundingBox->minX + $this->boundingBox->maxX) / 2;
        $this->y = $this->boundingBox->minY - $this->ySize;
        $this->z = ($this->boundingBox->minZ + $this->boundingBox->maxZ) / 2;
        $this->checkChunks();
//        $this->checkBlockCollision();
        $this->checkGroundState($movX, $movY, $movZ, $dx, $dy, $dz);
        $this->updateFallState($dy, $this->onGround);
        if($movX != $dx) {
            $this->motion->x = 0;
        }
        if($movY != $dy) {
            $this->motion->y = 0;
        }
        if($movZ != $dz) {
            $this->motion->z = 0;
        }
        //TODO: vehicle collision events (first we need to spawn them!)
        Timings::$entityMoveTimer->stopTiming();
    }
    public function sendSpawnItems(): void{
        $this->getInventory()->setItemInHand(Item::get(Item::DIAMOND_PICKAXE));
        //$this->getArmorInventory()->setHelmet( Item::get(Item::SKULL, 3));
        //$this->getArmorInventory()->setChestplate(Item::get(Item::LEATHER_CHESTPLATE));
        //$this->getArmorInventory()->setLeggings(Item::get(Item::LEATHER_LEGGINGS));
        //$this->getArmorInventory()->setBoots(Item::get(Item::LEATHER_BOOTS));
    }

    public function getLookingBlock(): Block{
        $block = Block::get(Block::AIR);
        switch($this->getDirection()){
            case 0:
                $block = $this->getLevel()->getBlock($this->add(1, 0, 0));
                break;
            case 1:
                $block = $this->getLevel()->getBlock($this->add(0, 0, 1));
                break;
            case 2:
                $block = $this->getLevel()->getBlock($this->add(-1, 0, 0));
                break;
            case 3:
                $block = $this->getLevel()->getBlock($this->add(0, 0, -1));
                break;
        }
        return $block;
    }

    public function getLookingBehind(): Block{
        $block = Block::get(Block::AIR);
        switch($this->getDirection()){
            case 0:
                $block = $this->getLevel()->getBlock($this->add(-1, 0, 0));
                break;
            case 1:
                $block = $this->getLevel()->getBlock($this->add(0, 0, -1));
                break;
            case 2:
                $block = $this->getLevel()->getBlock($this->add(1, 0, 0));
                break;
            case 3:
                $block = $this->getLevel()->getBlock($this->add(0, 0, 1));
                break;
        }
        return $block;
    }

    public function checkEverythingElse(): bool{
        $block = $this->getLookingBlock();
        $tile = $this->getLevel()->getTile($this->getLookingBehind());

        if($tile instanceof \pocketmine\tile\Chest){
            $inventory = $tile->getInventory();
            if($inventory->canAddItem(Item::get($block->getId(), $block->getDamage()))) return true;
        }
        return false;
    }

    public function breakBlock(Block $block): void{
        //$tile = $this->getLevel()->getTile($this->getLookingBehind());
        //if($tile instanceof \pocketmine\tile\Chest){
            $p = $this->getLevel()->getServer()->getPlayer($this->owner);
             if($p == null){return ;}
            $inv = $this->getLevel()->getServer()->getPlayer($this->owner)->getInventory();
           
            $inv->addItem(Item::get($block->getId(), $block->getDamage()));
        //}
        $this->getLevel()->setBlock($block, Block::get(Block::AIR), true, true);
    }

    public function getMineTime(): int{
        return 20 * $this->namedtag->getInt("Time");
    }

    public function flagForDespawn(): void{
        Main::ok($this->owner);
        
        parent::flagForDespawn();
        return;
        foreach($this->getDrops() as $drop){
            $this->getLevel()->dropItem($this->add(0.5, 0.5, 0.5), $drop);
        }
    }

    public function getCost(): int{
        return $this->getTime() + 2;
    }

    public function getTime(): int{
        return $this->namedtag->getInt("Time");
    }

    public function getDrops(): array{
        return [Main::get()->getItem()];
    }
}


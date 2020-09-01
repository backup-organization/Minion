<?php

declare(strict_types=1);

namespace CLADevs\Minion;

use CLADevs\Minion\minion\HopperInventory;
use CLADevs\Minion\minion\Minion;
use CLADevs\Minion\utils\Configuration;
use pocketmine\block\Chest;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\Server;

use pocketmine\utils\TextFormat;

class EventListener implements Listener{

    /** @var Minion[] */
    public $linkable = [];

    public function isLinkable(Player $player): bool{
        return isset($this->linkable[$player->getName()]);
    }

    public function addLinkable(Player $player, Minion $minion): bool{
        $overwrite = false;
        if($this->isLinkable($player)){
            $overwrite = true;
        }
        $this->linkable[$player->getName()] = $minion;
        return $overwrite;
    }

    public function removeLinkable(Player $player): void{
        if($this->isLinkable($player)) unset($this->linkable[$player->getName()]);
    }

    public function onInv(InventoryTransactionEvent $e): void{
        $tr = $e->getTransaction();
        foreach($tr->getActions() as $act){
            if($act instanceof SlotChangeAction){
                $inv = $act->getInventory();
                if($inv instanceof HopperInventory){
                    $player = $tr->getSource();
                    $e->setCancelled();
                    $inv->onListener($player, $act->getSourceItem(), $this);
                }
            }
        }
    }

    public function onInteract(PlayerInteractEvent $e): void{
        $player = $e->getPlayer();
        $item = $e->getItem();
        $dnbt = $item->getNamedTag();

        if($dnbt->hasTag("summon", StringTag::class) and !$e->isCancelled()){
            if(in_array($player->getLevel()->getFolderName(), Configuration::getNotAllowWorlds())){
                $player->sendMessage(TextFormat::RED . "You can't place minions in this world.");
                return;
            }
            $nbt = Entity::createBaseNBT($player, null, (90 + ($player->getDirection() * 90)) % 360);
            $nbt->setInt("level", $dnbt->getInt("level"));
            $nbt->setString("player", $player->getName());
            $nbt->setString("xyz", $dnbt->getString("xyz"));
            $nbt->setTag($player->namedtag->getTag("Skin"));
            $entity = new Minion($player->getLevel(), $nbt);
            $entity->spawnToAll();
            $item->setCount($item->getCount() - 1);
            $player->getInventory()->setItemInHand($item);
        }

        if($this->isLinkable($player)){
            $block = $e->getBlock();
            if(!$block instanceof Chest){
                $player->sendMessage(TextFormat::RED . "Please tap a chest not a " . $e->getBlock()->getName());
                return;
            }
            $entity = $this->linkable[$player->getName()];
            if($entity instanceof Minion) $entity->namedtag->setString("xyz", $block->getX() . ":" . $block->getY() . ":" . $block->getZ());
            $this->removeLinkable($player);
            $player->sendMessage(TextFormat::GREEN . "You have linked a chest!");
            return;
        }
    }

    public function onEntitySpawn(EntitySpawnEvent $e): void{
        $entity = $e->getEntity();

        if($entity instanceof Minion){
            $pl = Server::getInstance()->getPluginManager()->getPlugin("ClearLagg");
            if($pl !== null) $pl->exemptEntity($entity);
        }
    }
}

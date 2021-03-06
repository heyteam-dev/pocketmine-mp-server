<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockIdentifierFlattened as BIDFlattened;
use pocketmine\block\BlockLegacyIds as Ids;
use pocketmine\block\BlockLegacyMetadata as Meta;
use pocketmine\block\tile\Banner as TileBanner;
use pocketmine\block\tile\Barrel as TileBarrel;
use pocketmine\block\tile\Beacon as TileBeacon;
use pocketmine\block\tile\Bed as TileBed;
use pocketmine\block\tile\BrewingStand as TileBrewingStand;
use pocketmine\block\tile\Chest as TileChest;
use pocketmine\block\tile\Comparator as TileComparator;
use pocketmine\block\tile\DaylightSensor as TileDaylightSensor;
use pocketmine\block\tile\EnchantTable as TileEnchantingTable;
use pocketmine\block\tile\EnderChest as TileEnderChest;
use pocketmine\block\tile\FlowerPot as TileFlowerPot;
use pocketmine\block\tile\Furnace as TileFurnace;
use pocketmine\block\tile\Hopper as TileHopper;
use pocketmine\block\tile\ItemFrame as TileItemFrame;
use pocketmine\block\tile\Jukebox as TileJukebox;
use pocketmine\block\tile\MonsterSpawner as TileMonsterSpawner;
use pocketmine\block\tile\Note as TileNote;
use pocketmine\block\tile\Skull as TileSkull;
use pocketmine\block\utils\CoralType;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\InvalidBlockStateException;
use pocketmine\block\utils\TreeType;
use pocketmine\data\bedrock\CoralTypeIdMap;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\item\ToolTier;
use pocketmine\utils\SingletonTrait;
use function array_fill;
use function array_filter;
use function get_class;
use function min;

/**
 * Manages deserializing block types from their legacy blockIDs and metadata.
 * This is primarily needed for loading chunks from disk.
 */
class BlockFactory{
	use SingletonTrait;

	/**
	 * @var \SplFixedArray|Block[]
	 * @phpstan-var \SplFixedArray<Block>
	 */
	private $fullList;

	/**
	 * @var \SplFixedArray|int[]
	 * @phpstan-var \SplFixedArray<int>
	 */
	public $light;
	/**
	 * @var \SplFixedArray|int[]
	 * @phpstan-var \SplFixedArray<int>
	 */
	public $lightFilter;
	/**
	 * @var \SplFixedArray|bool[]
	 * @phpstan-var \SplFixedArray<bool>
	 */
	public $blocksDirectSkyLight;
	/**
	 * @var \SplFixedArray|float[]
	 * @phpstan-var \SplFixedArray<float>
	 */
	public $blastResistance;

	public function __construct(){
		$this->fullList = new \SplFixedArray(16384);

		$this->light = \SplFixedArray::fromArray(array_fill(0, 16384, 0));
		$this->lightFilter = \SplFixedArray::fromArray(array_fill(0, 16384, 1));
		$this->blocksDirectSkyLight = \SplFixedArray::fromArray(array_fill(0, 16384, false));
		$this->blastResistance = \SplFixedArray::fromArray(array_fill(0, 16384, 0.0));

		$this->register(new ActivatorRail(new BID(Ids::ACTIVATOR_RAIL, 0), "Activator Rail"));
		$this->register(new Air(new BID(Ids::AIR, 0), "Air"));
		$this->register(new Anvil(new BID(Ids::ANVIL, 0), "Anvil"));
		$this->register(new Bamboo(new BID(Ids::BAMBOO, 0), "Bamboo", new BlockBreakInfo(2.0 /* 1.0 in PC */, BlockToolType::AXE)));
		$this->register(new BambooSapling(new BID(Ids::BAMBOO_SAPLING, 0), "Bamboo Sapling", BlockBreakInfo::instant()));
		$this->register(new FloorBanner(new BID(Ids::STANDING_BANNER, 0, ItemIds::BANNER, TileBanner::class), "Banner"));
		$this->register(new WallBanner(new BID(Ids::WALL_BANNER, 0, ItemIds::BANNER, TileBanner::class), "Wall Banner"));
		$this->register(new Barrel(new BID(Ids::BARREL, 0, null, TileBarrel::class), "Barrel"));
		$this->register(new Transparent(new BID(Ids::BARRIER, 0), "Barrier", BlockBreakInfo::indestructible()));
		$this->register(new Beacon(new BID(Ids::BEACON, 0, null, TileBeacon::class), "Beacon", new BlockBreakInfo(3.0)));
		$this->register(new Bed(new BID(Ids::BED_BLOCK, 0, ItemIds::BED, TileBed::class), "Bed Block"));
		$this->register(new Bedrock(new BID(Ids::BEDROCK, 0), "Bedrock"));
		$this->register(new Beetroot(new BID(Ids::BEETROOT_BLOCK, 0), "Beetroot Block"));
		$this->register(new BlueIce(new BID(Ids::BLUE_ICE, 0), "Blue Ice"));
		$this->register(new BoneBlock(new BID(Ids::BONE_BLOCK, 0), "Bone Block"));
		$this->register(new Bookshelf(new BID(Ids::BOOKSHELF, 0), "Bookshelf"));
		$this->register(new BrewingStand(new BID(Ids::BREWING_STAND_BLOCK, 0, ItemIds::BREWING_STAND, TileBrewingStand::class), "Brewing Stand"));

		$bricksBreakInfo = new BlockBreakInfo(2.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 30.0);
		$this->register(new Stair(new BID(Ids::BRICK_STAIRS, 0), "Brick Stairs", $bricksBreakInfo));
		$this->register(new Opaque(new BID(Ids::BRICK_BLOCK, 0), "Bricks", $bricksBreakInfo));

		$this->register(new BrownMushroom(new BID(Ids::BROWN_MUSHROOM, 0), "Brown Mushroom"));
		$this->register(new Cactus(new BID(Ids::CACTUS, 0), "Cactus"));
		$this->register(new Cake(new BID(Ids::CAKE_BLOCK, 0, ItemIds::CAKE), "Cake"));
		$this->register(new Carrot(new BID(Ids::CARROTS, 0), "Carrot Block"));
		$this->register(new Chest(new BID(Ids::CHEST, 0, null, TileChest::class), "Chest"));
		$this->register(new Clay(new BID(Ids::CLAY_BLOCK, 0), "Clay Block"));
		$this->register(new Coal(new BID(Ids::COAL_BLOCK, 0), "Coal Block"));
		$this->register(new CoalOre(new BID(Ids::COAL_ORE, 0), "Coal Ore"));
		$this->register(new CoarseDirt(new BID(Ids::DIRT, Meta::DIRT_COARSE), "Coarse Dirt"));

		$cobblestoneBreakInfo = new BlockBreakInfo(2.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 30.0);
		$this->register($cobblestone = new Opaque(new BID(Ids::COBBLESTONE, 0), "Cobblestone", $cobblestoneBreakInfo));
		$this->register(new InfestedStone(new BID(Ids::MONSTER_EGG, Meta::INFESTED_COBBLESTONE), "Infested Cobblestone", $cobblestone));
		$this->register(new Opaque(new BID(Ids::MOSSY_COBBLESTONE, 0), "Mossy Cobblestone", $cobblestoneBreakInfo));
		$this->register(new Stair(new BID(Ids::COBBLESTONE_STAIRS, 0), "Cobblestone Stairs", $cobblestoneBreakInfo));
		$this->register(new Stair(new BID(Ids::MOSSY_COBBLESTONE_STAIRS, 0), "Mossy Cobblestone Stairs", $cobblestoneBreakInfo));

		$this->register(new Cobweb(new BID(Ids::COBWEB, 0), "Cobweb"));
		$this->register(new CocoaBlock(new BID(Ids::COCOA, 0), "Cocoa Block"));
		$this->register(new CoralBlock(new BID(Ids::CORAL_BLOCK, 0), "Coral Block", new BlockBreakInfo(7.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel())));
		$this->register(new CraftingTable(new BID(Ids::CRAFTING_TABLE, 0), "Crafting Table"));
		$this->register(new DaylightSensor(new BIDFlattened(Ids::DAYLIGHT_DETECTOR, Ids::DAYLIGHT_DETECTOR_INVERTED, 0, null, TileDaylightSensor::class), "Daylight Sensor"));
		$this->register(new DeadBush(new BID(Ids::DEADBUSH, 0), "Dead Bush"));
		$this->register(new DetectorRail(new BID(Ids::DETECTOR_RAIL, 0), "Detector Rail"));

		$this->register(new Opaque(new BID(Ids::DIAMOND_BLOCK, 0), "Diamond Block", new BlockBreakInfo(5.0, BlockToolType::PICKAXE, ToolTier::IRON()->getHarvestLevel(), 30.0)));
		$this->register(new DiamondOre(new BID(Ids::DIAMOND_ORE, 0), "Diamond Ore"));
		$this->register(new Dirt(new BID(Ids::DIRT, Meta::DIRT_NORMAL), "Dirt"));
		$this->register(new DoublePlant(new BID(Ids::DOUBLE_PLANT, Meta::DOUBLE_PLANT_SUNFLOWER), "Sunflower"));
		$this->register(new DoublePlant(new BID(Ids::DOUBLE_PLANT, Meta::DOUBLE_PLANT_LILAC), "Lilac"));
		$this->register(new DoublePlant(new BID(Ids::DOUBLE_PLANT, Meta::DOUBLE_PLANT_ROSE_BUSH), "Rose Bush"));
		$this->register(new DoublePlant(new BID(Ids::DOUBLE_PLANT, Meta::DOUBLE_PLANT_PEONY), "Peony"));
		$this->register(new DoubleTallGrass(new BID(Ids::DOUBLE_PLANT, Meta::DOUBLE_PLANT_TALLGRASS), "Double Tallgrass"));
		$this->register(new DoubleTallGrass(new BID(Ids::DOUBLE_PLANT, Meta::DOUBLE_PLANT_LARGE_FERN), "Large Fern"));
		$this->register(new DragonEgg(new BID(Ids::DRAGON_EGG, 0), "Dragon Egg"));
		$this->register(new DriedKelp(new BID(Ids::DRIED_KELP_BLOCK, 0), "Dried Kelp Block", new BlockBreakInfo(0.5, BlockToolType::NONE, 0, 12.5)));
		$this->register(new Opaque(new BID(Ids::EMERALD_BLOCK, 0), "Emerald Block", new BlockBreakInfo(5.0, BlockToolType::PICKAXE, ToolTier::IRON()->getHarvestLevel(), 30.0)));
		$this->register(new EmeraldOre(new BID(Ids::EMERALD_ORE, 0), "Emerald Ore"));
		$this->register(new EnchantingTable(new BID(Ids::ENCHANTING_TABLE, 0, null, TileEnchantingTable::class), "Enchanting Table"));
		$this->register(new EndPortalFrame(new BID(Ids::END_PORTAL_FRAME, 0), "End Portal Frame"));
		$this->register(new EndRod(new BID(Ids::END_ROD, 0), "End Rod"));
		$this->register(new Opaque(new BID(Ids::END_STONE, 0), "End Stone", new BlockBreakInfo(3.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 45.0)));

		$endBrickBreakInfo = new BlockBreakInfo(0.8, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 4.0);
		$this->register(new Opaque(new BID(Ids::END_BRICKS, 0), "End Stone Bricks", $endBrickBreakInfo));
		$this->register(new Stair(new BID(Ids::END_BRICK_STAIRS, 0), "End Stone Brick Stairs", $endBrickBreakInfo));

		$this->register(new EnderChest(new BID(Ids::ENDER_CHEST, 0, null, TileEnderChest::class), "Ender Chest"));
		$this->register(new Farmland(new BID(Ids::FARMLAND, 0), "Farmland"));
		$this->register(new Fire(new BID(Ids::FIRE, 0), "Fire Block"));
		$this->register(new Flower(new BID(Ids::DANDELION, 0), "Dandelion"));
		$this->register(new Flower(new BID(Ids::RED_FLOWER, Meta::FLOWER_ALLIUM), "Allium"));
		$this->register(new Flower(new BID(Ids::RED_FLOWER, Meta::FLOWER_AZURE_BLUET), "Azure Bluet"));
		$this->register(new Flower(new BID(Ids::RED_FLOWER, Meta::FLOWER_BLUE_ORCHID), "Blue Orchid"));
		$this->register(new Flower(new BID(Ids::RED_FLOWER, Meta::FLOWER_CORNFLOWER), "Cornflower"));
		$this->register(new Flower(new BID(Ids::RED_FLOWER, Meta::FLOWER_LILY_OF_THE_VALLEY), "Lily of the Valley"));
		$this->register(new Flower(new BID(Ids::RED_FLOWER, Meta::FLOWER_ORANGE_TULIP), "Orange Tulip"));
		$this->register(new Flower(new BID(Ids::RED_FLOWER, Meta::FLOWER_OXEYE_DAISY), "Oxeye Daisy"));
		$this->register(new Flower(new BID(Ids::RED_FLOWER, Meta::FLOWER_PINK_TULIP), "Pink Tulip"));
		$this->register(new Flower(new BID(Ids::RED_FLOWER, Meta::FLOWER_POPPY), "Poppy"));
		$this->register(new Flower(new BID(Ids::RED_FLOWER, Meta::FLOWER_RED_TULIP), "Red Tulip"));
		$this->register(new Flower(new BID(Ids::RED_FLOWER, Meta::FLOWER_WHITE_TULIP), "White Tulip"));
		$this->register(new FlowerPot(new BID(Ids::FLOWER_POT_BLOCK, 0, ItemIds::FLOWER_POT, TileFlowerPot::class), "Flower Pot"));
		$this->register(new FrostedIce(new BID(Ids::FROSTED_ICE, 0), "Frosted Ice"));
		$this->register(new Furnace(new BIDFlattened(Ids::FURNACE, Ids::LIT_FURNACE, 0, null, TileFurnace::class), "Furnace"));
		$this->register(new Glass(new BID(Ids::GLASS, 0), "Glass"));
		$this->register(new GlassPane(new BID(Ids::GLASS_PANE, 0), "Glass Pane"));
		$this->register(new GlowingObsidian(new BID(Ids::GLOWINGOBSIDIAN, 0), "Glowing Obsidian"));
		$this->register(new Glowstone(new BID(Ids::GLOWSTONE, 0), "Glowstone"));
		$this->register(new Opaque(new BID(Ids::GOLD_BLOCK, 0), "Gold Block", new BlockBreakInfo(3.0, BlockToolType::PICKAXE, ToolTier::IRON()->getHarvestLevel(), 30.0)));
		$this->register(new Opaque(new BID(Ids::GOLD_ORE, 0), "Gold Ore", new BlockBreakInfo(3.0, BlockToolType::PICKAXE, ToolTier::IRON()->getHarvestLevel())));
		$this->register(new Grass(new BID(Ids::GRASS, 0), "Grass"));
		$this->register(new GrassPath(new BID(Ids::GRASS_PATH, 0), "Grass Path"));
		$this->register(new Gravel(new BID(Ids::GRAVEL, 0), "Gravel"));
		$this->register(new HardenedClay(new BID(Ids::HARDENED_CLAY, 0), "Hardened Clay"));
		$this->register(new HardenedGlass(new BID(Ids::HARD_GLASS, 0), "Hardened Glass"));
		$this->register(new HardenedGlassPane(new BID(Ids::HARD_GLASS_PANE, 0), "Hardened Glass Pane"));
		$this->register(new HayBale(new BID(Ids::HAY_BALE, 0), "Hay Bale"));
		$this->register(new Hopper(new BID(Ids::HOPPER_BLOCK, 0, ItemIds::HOPPER, TileHopper::class), "Hopper", new BlockBreakInfo(3.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 15.0)));
		$this->register(new Ice(new BID(Ids::ICE, 0), "Ice"));

		$updateBlockBreakInfo = new BlockBreakInfo(1.0);
		$this->register(new Opaque(new BID(Ids::INFO_UPDATE, 0), "update!", $updateBlockBreakInfo));
		$this->register(new Opaque(new BID(Ids::INFO_UPDATE2, 0), "ate!upd", $updateBlockBreakInfo));
		$this->register(new Transparent(new BID(Ids::INVISIBLEBEDROCK, 0), "Invisible Bedrock", BlockBreakInfo::indestructible()));
		$this->register(new Opaque(new BID(Ids::IRON_BLOCK, 0), "Iron Block", new BlockBreakInfo(5.0, BlockToolType::PICKAXE, ToolTier::STONE()->getHarvestLevel(), 30.0)));
		$this->register(new Thin(new BID(Ids::IRON_BARS, 0), "Iron Bars", new BlockBreakInfo(5.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 30.0)));
		$this->register(new Door(new BID(Ids::IRON_DOOR_BLOCK, 0, ItemIds::IRON_DOOR), "Iron Door", new BlockBreakInfo(5.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 25.0)));
		$this->register(new Opaque(new BID(Ids::IRON_ORE, 0), "Iron Ore", new BlockBreakInfo(3.0, BlockToolType::PICKAXE, ToolTier::STONE()->getHarvestLevel())));
		$this->register(new Trapdoor(new BID(Ids::IRON_TRAPDOOR, 0), "Iron Trapdoor", new BlockBreakInfo(5.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 25.0)));
		$this->register(new ItemFrame(new BID(Ids::FRAME_BLOCK, 0, ItemIds::FRAME, TileItemFrame::class), "Item Frame"));
		$this->register(new Jukebox(new BID(Ids::JUKEBOX, 0, ItemIds::JUKEBOX, TileJukebox::class), "Jukebox"));
		$this->register(new Ladder(new BID(Ids::LADDER, 0), "Ladder"));
		$this->register(new Lantern(new BID(Ids::LANTERN, 0), "Lantern", new BlockBreakInfo(5.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel())));
		$this->register(new Opaque(new BID(Ids::LAPIS_BLOCK, 0), "Lapis Lazuli Block", new BlockBreakInfo(3.0, BlockToolType::PICKAXE, ToolTier::STONE()->getHarvestLevel())));
		$this->register(new LapisOre(new BID(Ids::LAPIS_ORE, 0), "Lapis Lazuli Ore"));
		$this->register(new Lava(new BIDFlattened(Ids::FLOWING_LAVA, Ids::STILL_LAVA, 0), "Lava"));
		$this->register(new Lever(new BID(Ids::LEVER, 0), "Lever"));
		$this->register(new Loom(new BID(Ids::LOOM, 0), "Loom", new BlockBreakInfo(2.5, BlockToolType::AXE)));
		$this->register(new Magma(new BID(Ids::MAGMA, 0), "Magma Block"));
		$this->register(new Melon(new BID(Ids::MELON_BLOCK, 0), "Melon Block"));
		$this->register(new MelonStem(new BID(Ids::MELON_STEM, 0, ItemIds::MELON_SEEDS), "Melon Stem"));
		$this->register(new MonsterSpawner(new BID(Ids::MOB_SPAWNER, 0, null, TileMonsterSpawner::class), "Monster Spawner"));
		$this->register(new Mycelium(new BID(Ids::MYCELIUM, 0), "Mycelium"));

		$netherBrickBreakInfo = new BlockBreakInfo(2.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 30.0);
		$this->register(new Opaque(new BID(Ids::NETHER_BRICK_BLOCK, 0), "Nether Bricks", $netherBrickBreakInfo));
		$this->register(new Opaque(new BID(Ids::RED_NETHER_BRICK, 0), "Red Nether Bricks", $netherBrickBreakInfo));
		$this->register(new Fence(new BID(Ids::NETHER_BRICK_FENCE, 0), "Nether Brick Fence", $netherBrickBreakInfo));
		$this->register(new Stair(new BID(Ids::NETHER_BRICK_STAIRS, 0), "Nether Brick Stairs", $netherBrickBreakInfo));
		$this->register(new Stair(new BID(Ids::RED_NETHER_BRICK_STAIRS, 0), "Red Nether Brick Stairs", $netherBrickBreakInfo));
		$this->register(new NetherPortal(new BID(Ids::PORTAL, 0), "Nether Portal"));
		$this->register(new NetherQuartzOre(new BID(Ids::NETHER_QUARTZ_ORE, 0), "Nether Quartz Ore"));
		$this->register(new NetherReactor(new BID(Ids::NETHERREACTOR, 0), "Nether Reactor Core"));
		$this->register(new Opaque(new BID(Ids::NETHER_WART_BLOCK, 0), "Nether Wart Block", new BlockBreakInfo(1.0)));
		$this->register(new NetherWartPlant(new BID(Ids::NETHER_WART_PLANT, 0, ItemIds::NETHER_WART), "Nether Wart"));
		$this->register(new Netherrack(new BID(Ids::NETHERRACK, 0), "Netherrack"));
		$this->register(new Note(new BID(Ids::NOTEBLOCK, 0, null, TileNote::class), "Note Block"));
		$this->register(new Opaque(new BID(Ids::OBSIDIAN, 0), "Obsidian", new BlockBreakInfo(35.0 /* 50 in PC */, BlockToolType::PICKAXE, ToolTier::DIAMOND()->getHarvestLevel(), 6000.0)));
		$this->register(new PackedIce(new BID(Ids::PACKED_ICE, 0), "Packed Ice"));
		$this->register(new Podzol(new BID(Ids::PODZOL, 0), "Podzol"));
		$this->register(new Potato(new BID(Ids::POTATOES, 0), "Potato Block"));
		$this->register(new PoweredRail(new BID(Ids::GOLDEN_RAIL, Meta::RAIL_STRAIGHT_NORTH_SOUTH), "Powered Rail"));

		$prismarineBreakInfo = new BlockBreakInfo(1.5, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 30.0);
		$this->register(new Opaque(new BID(Ids::PRISMARINE, Meta::PRISMARINE_BRICKS), "Prismarine Bricks", $prismarineBreakInfo));
		$this->register(new Stair(new BID(Ids::PRISMARINE_BRICKS_STAIRS, 0), "Prismarine Bricks Stairs", $prismarineBreakInfo));
		$this->register(new Opaque(new BID(Ids::PRISMARINE, Meta::PRISMARINE_DARK), "Dark Prismarine", $prismarineBreakInfo));
		$this->register(new Stair(new BID(Ids::DARK_PRISMARINE_STAIRS, 0), "Dark Prismarine Stairs", $prismarineBreakInfo));
		$this->register(new Opaque(new BID(Ids::PRISMARINE, Meta::PRISMARINE_NORMAL), "Prismarine", $prismarineBreakInfo));
		$this->register(new Stair(new BID(Ids::PRISMARINE_STAIRS, 0), "Prismarine Stairs", $prismarineBreakInfo));

		$pumpkinBreakInfo = new BlockBreakInfo(1.0, BlockToolType::AXE);
		$this->register($pumpkin = new Opaque(new BID(Ids::PUMPKIN, 0), "Pumpkin", $pumpkinBreakInfo));
		for($i = 1; $i <= 3; ++$i){
			$this->remap(Ids::PUMPKIN, $i, $pumpkin);
		}
		$this->register(new CarvedPumpkin(new BID(Ids::CARVED_PUMPKIN, 0), "Carved Pumpkin", $pumpkinBreakInfo));
		$this->register(new LitPumpkin(new BID(Ids::JACK_O_LANTERN, 0), "Jack o'Lantern", $pumpkinBreakInfo));

		$this->register(new PumpkinStem(new BID(Ids::PUMPKIN_STEM, 0, ItemIds::PUMPKIN_SEEDS), "Pumpkin Stem"));

		$purpurBreakInfo = new BlockBreakInfo(1.5, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 30.0);
		$this->register(new Opaque(new BID(Ids::PURPUR_BLOCK, Meta::PURPUR_NORMAL), "Purpur Block", $purpurBreakInfo));
		$this->register(new SimplePillar(new BID(Ids::PURPUR_BLOCK, Meta::PURPUR_PILLAR), "Purpur Pillar", $purpurBreakInfo));
		$this->register(new Stair(new BID(Ids::PURPUR_STAIRS, 0), "Purpur Stairs", $purpurBreakInfo));

		$quartzBreakInfo = new BlockBreakInfo(0.8, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel());
		$this->register(new Opaque(new BID(Ids::QUARTZ_BLOCK, Meta::QUARTZ_NORMAL), "Quartz Block", $quartzBreakInfo));
		$this->register(new Stair(new BID(Ids::QUARTZ_STAIRS, 0), "Quartz Stairs", $quartzBreakInfo));
		$this->register(new SimplePillar(new BID(Ids::QUARTZ_BLOCK, Meta::QUARTZ_CHISELED), "Chiseled Quartz Block", $quartzBreakInfo));
		$this->register(new SimplePillar(new BID(Ids::QUARTZ_BLOCK, Meta::QUARTZ_PILLAR), "Quartz Pillar", $quartzBreakInfo));
		$this->register(new Opaque(new BID(Ids::QUARTZ_BLOCK, Meta::QUARTZ_SMOOTH), "Smooth Quartz Block", $quartzBreakInfo)); //TODO: this has axis rotation in 1.9, unsure if a bug (https://bugs.mojang.com/browse/MCPE-39074)
		$this->register(new Stair(new BID(Ids::SMOOTH_QUARTZ_STAIRS, 0), "Smooth Quartz Stairs", $quartzBreakInfo));

		$this->register(new Rail(new BID(Ids::RAIL, 0), "Rail"));
		$this->register(new RedMushroom(new BID(Ids::RED_MUSHROOM, 0), "Red Mushroom"));
		$this->register(new Redstone(new BID(Ids::REDSTONE_BLOCK, 0), "Redstone Block"));
		$this->register(new RedstoneComparator(new BIDFlattened(Ids::UNPOWERED_COMPARATOR, Ids::POWERED_COMPARATOR, 0, ItemIds::COMPARATOR, TileComparator::class), "Redstone Comparator"));
		$this->register(new RedstoneLamp(new BIDFlattened(Ids::REDSTONE_LAMP, Ids::LIT_REDSTONE_LAMP, 0), "Redstone Lamp"));
		$this->register(new RedstoneOre(new BIDFlattened(Ids::REDSTONE_ORE, Ids::LIT_REDSTONE_ORE, 0), "Redstone Ore"));
		$this->register(new RedstoneRepeater(new BIDFlattened(Ids::UNPOWERED_REPEATER, Ids::POWERED_REPEATER, 0, ItemIds::REPEATER), "Redstone Repeater"));
		$this->register(new RedstoneTorch(new BIDFlattened(Ids::REDSTONE_TORCH, Ids::UNLIT_REDSTONE_TORCH, 0), "Redstone Torch"));
		$this->register(new RedstoneWire(new BID(Ids::REDSTONE_WIRE, 0, ItemIds::REDSTONE), "Redstone"));
		$this->register(new Reserved6(new BID(Ids::RESERVED6, 0), "reserved6"));
		$this->register(new Sand(new BID(Ids::SAND, 0), "Sand"));
		$this->register(new Sand(new BID(Ids::SAND, 1), "Red Sand"));
		$this->register(new SeaLantern(new BID(Ids::SEALANTERN, 0), "Sea Lantern"));
		$this->register(new SeaPickle(new BID(Ids::SEA_PICKLE, 0), "Sea Pickle"));
		$this->register(new Skull(new BID(Ids::MOB_HEAD_BLOCK, 0, null, TileSkull::class), "Mob Head"));

		$this->register(new Snow(new BID(Ids::SNOW, 0), "Snow Block"));
		$this->register(new SnowLayer(new BID(Ids::SNOW_LAYER, 0), "Snow Layer"));
		$this->register(new SoulSand(new BID(Ids::SOUL_SAND, 0), "Soul Sand"));
		$this->register(new Sponge(new BID(Ids::SPONGE, 0), "Sponge"));

		$stoneBreakInfo = new BlockBreakInfo(1.5, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 30.0);
		$this->register($stone = new class(new BID(Ids::STONE, Meta::STONE_NORMAL), "Stone", $stoneBreakInfo) extends Opaque{
			public function getDropsForCompatibleTool(Item $item) : array{
				return [VanillaBlocks::COBBLESTONE()->asItem()];
			}

			public function isAffectedBySilkTouch() : bool{
				return true;
			}
		});
		$this->register(new InfestedStone(new BID(Ids::MONSTER_EGG, Meta::INFESTED_STONE), "Infested Stone", $stone));
		$this->register(new Stair(new BID(Ids::NORMAL_STONE_STAIRS, 0), "Stone Stairs", $stoneBreakInfo));
		$this->register(new Opaque(new BID(Ids::SMOOTH_STONE, 0), "Smooth Stone", $stoneBreakInfo));
		$this->register(new Opaque(new BID(Ids::STONE, Meta::STONE_ANDESITE), "Andesite", $stoneBreakInfo));
		$this->register(new Stair(new BID(Ids::ANDESITE_STAIRS, 0), "Andesite Stairs", $stoneBreakInfo));
		$this->register(new Opaque(new BID(Ids::STONE, Meta::STONE_DIORITE), "Diorite", $stoneBreakInfo));
		$this->register(new Stair(new BID(Ids::DIORITE_STAIRS, 0), "Diorite Stairs", $stoneBreakInfo));
		$this->register(new Opaque(new BID(Ids::STONE, Meta::STONE_GRANITE), "Granite", $stoneBreakInfo));
		$this->register(new Stair(new BID(Ids::GRANITE_STAIRS, 0), "Granite Stairs", $stoneBreakInfo));
		$this->register(new Opaque(new BID(Ids::STONE, Meta::STONE_POLISHED_ANDESITE), "Polished Andesite", $stoneBreakInfo));
		$this->register(new Stair(new BID(Ids::POLISHED_ANDESITE_STAIRS, 0), "Polished Andesite Stairs", $stoneBreakInfo));
		$this->register(new Opaque(new BID(Ids::STONE, Meta::STONE_POLISHED_DIORITE), "Polished Diorite", $stoneBreakInfo));
		$this->register(new Stair(new BID(Ids::POLISHED_DIORITE_STAIRS, 0), "Polished Diorite Stairs", $stoneBreakInfo));
		$this->register(new Opaque(new BID(Ids::STONE, Meta::STONE_POLISHED_GRANITE), "Polished Granite", $stoneBreakInfo));
		$this->register(new Stair(new BID(Ids::POLISHED_GRANITE_STAIRS, 0), "Polished Granite Stairs", $stoneBreakInfo));
		$this->register(new Stair(new BID(Ids::STONE_BRICK_STAIRS, 0), "Stone Brick Stairs", $stoneBreakInfo));
		$this->register($chiseledStoneBrick = new Opaque(new BID(Ids::STONEBRICK, Meta::STONE_BRICK_CHISELED), "Chiseled Stone Bricks", $stoneBreakInfo));
		$this->register(new InfestedStone(new BID(Ids::MONSTER_EGG, Meta::INFESTED_STONE_BRICK_CHISELED), "Infested Chiseled Stone Brick", $chiseledStoneBrick));
		$this->register($crackedStoneBrick = new Opaque(new BID(Ids::STONEBRICK, Meta::STONE_BRICK_CRACKED), "Cracked Stone Bricks", $stoneBreakInfo));
		$this->register(new InfestedStone(new BID(Ids::MONSTER_EGG, Meta::INFESTED_STONE_BRICK_CRACKED), "Infested Cracked Stone Brick", $crackedStoneBrick));
		$this->register($mossyStoneBrick = new Opaque(new BID(Ids::STONEBRICK, Meta::STONE_BRICK_MOSSY), "Mossy Stone Bricks", $stoneBreakInfo));
		$this->register(new InfestedStone(new BID(Ids::MONSTER_EGG, Meta::INFESTED_STONE_BRICK_MOSSY), "Infested Mossy Stone Brick", $mossyStoneBrick));
		$this->register(new Stair(new BID(Ids::MOSSY_STONE_BRICK_STAIRS, 0), "Mossy Stone Brick Stairs", $stoneBreakInfo));
		$this->register($stoneBrick = new Opaque(new BID(Ids::STONEBRICK, Meta::STONE_BRICK_NORMAL), "Stone Bricks", $stoneBreakInfo));
		$this->register(new InfestedStone(new BID(Ids::MONSTER_EGG, Meta::INFESTED_STONE_BRICK), "Infested Stone Brick", $stoneBrick));
		$this->register(new StoneButton(new BID(Ids::STONE_BUTTON, 0), "Stone Button"));
		$this->register(new StonePressurePlate(new BID(Ids::STONE_PRESSURE_PLATE, 0), "Stone Pressure Plate"));

		//TODO: in the future this won't be the same for all the types
		$stoneSlabBreakInfo = new BlockBreakInfo(2.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 30.0);
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(1, Meta::STONE_SLAB_BRICK), "Brick", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(1, Meta::STONE_SLAB_COBBLESTONE), "Cobblestone", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(1, Meta::STONE_SLAB_FAKE_WOODEN), "Fake Wooden", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(1, Meta::STONE_SLAB_NETHER_BRICK), "Nether Brick", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(1, Meta::STONE_SLAB_QUARTZ), "Quartz", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(1, Meta::STONE_SLAB_SANDSTONE), "Sandstone", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(1, Meta::STONE_SLAB_SMOOTH_STONE), "Smooth Stone", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(1, Meta::STONE_SLAB_STONE_BRICK), "Stone Brick", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(2, Meta::STONE_SLAB2_DARK_PRISMARINE), "Dark Prismarine", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(2, Meta::STONE_SLAB2_MOSSY_COBBLESTONE), "Mossy Cobblestone", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(2, Meta::STONE_SLAB2_PRISMARINE), "Prismarine", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(2, Meta::STONE_SLAB2_PRISMARINE_BRICKS), "Prismarine Bricks", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(2, Meta::STONE_SLAB2_PURPUR), "Purpur", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(2, Meta::STONE_SLAB2_RED_NETHER_BRICK), "Red Nether Brick", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(2, Meta::STONE_SLAB2_RED_SANDSTONE), "Red Sandstone", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(2, Meta::STONE_SLAB2_SMOOTH_SANDSTONE), "Smooth Sandstone", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(3, Meta::STONE_SLAB3_ANDESITE), "Andesite", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(3, Meta::STONE_SLAB3_DIORITE), "Diorite", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(3, Meta::STONE_SLAB3_END_STONE_BRICK), "End Stone Brick", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(3, Meta::STONE_SLAB3_GRANITE), "Granite", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(3, Meta::STONE_SLAB3_POLISHED_ANDESITE), "Polished Andesite", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(3, Meta::STONE_SLAB3_POLISHED_DIORITE), "Polished Diorite", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(3, Meta::STONE_SLAB3_POLISHED_GRANITE), "Polished Granite", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(3, Meta::STONE_SLAB3_SMOOTH_RED_SANDSTONE), "Smooth Red Sandstone", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(4, Meta::STONE_SLAB4_CUT_RED_SANDSTONE), "Cut Red Sandstone", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(4, Meta::STONE_SLAB4_CUT_SANDSTONE), "Cut Sandstone", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(4, Meta::STONE_SLAB4_MOSSY_STONE_BRICK), "Mossy Stone Brick", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(4, Meta::STONE_SLAB4_SMOOTH_QUARTZ), "Smooth Quartz", $stoneSlabBreakInfo));
		$this->register(new Slab(BlockLegacyIdHelper::getStoneSlabIdentifier(4, Meta::STONE_SLAB4_STONE), "Stone", $stoneSlabBreakInfo));
		$this->register(new Opaque(new BID(Ids::STONECUTTER, 0), "Stonecutter", new BlockBreakInfo(3.5, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel())));
		$this->register(new Sugarcane(new BID(Ids::REEDS_BLOCK, 0, ItemIds::REEDS), "Sugarcane"));
		$this->register(new TNT(new BID(Ids::TNT, 0), "TNT"));

		$fern = new TallGrass(new BID(Ids::TALLGRASS, Meta::TALLGRASS_FERN), "Fern");
		$this->register($fern);
		$this->remap(Ids::TALLGRASS, 0, $fern);
		$this->remap(Ids::TALLGRASS, 3, $fern);
		$this->register(new TallGrass(new BID(Ids::TALLGRASS, Meta::TALLGRASS_NORMAL), "Tall Grass"));
		$this->register(new Torch(new BID(Ids::COLORED_TORCH_BP, 0), "Blue Torch"));
		$this->register(new Torch(new BID(Ids::COLORED_TORCH_BP, 8), "Purple Torch"));
		$this->register(new Torch(new BID(Ids::COLORED_TORCH_RG, 0), "Red Torch"));
		$this->register(new Torch(new BID(Ids::COLORED_TORCH_RG, 8), "Green Torch"));
		$this->register(new Torch(new BID(Ids::TORCH, 0), "Torch"));
		$this->register(new TrappedChest(new BID(Ids::TRAPPED_CHEST, 0, null, TileChest::class), "Trapped Chest"));
		$this->register(new Tripwire(new BID(Ids::TRIPWIRE, 0, ItemIds::STRING), "Tripwire"));
		$this->register(new TripwireHook(new BID(Ids::TRIPWIRE_HOOK, 0), "Tripwire Hook"));
		$this->register(new UnderwaterTorch(new BID(Ids::UNDERWATER_TORCH, 0), "Underwater Torch"));
		$this->register(new Vine(new BID(Ids::VINE, 0), "Vines"));
		$this->register(new Water(new BIDFlattened(Ids::FLOWING_WATER, Ids::STILL_WATER, 0), "Water"));
		$this->register(new WaterLily(new BID(Ids::LILY_PAD, 0), "Lily Pad"));
		$this->register(new WeightedPressurePlateHeavy(new BID(Ids::HEAVY_WEIGHTED_PRESSURE_PLATE, 0), "Weighted Pressure Plate Heavy"));
		$this->register(new WeightedPressurePlateLight(new BID(Ids::LIGHT_WEIGHTED_PRESSURE_PLATE, 0), "Weighted Pressure Plate Light"));
		$this->register(new Wheat(new BID(Ids::WHEAT_BLOCK, 0), "Wheat Block"));

		foreach(TreeType::getAll() as $treeType){
			$magicNumber = $treeType->getMagicNumber();
			$name = $treeType->getDisplayName();
			$this->register(new Planks(new BID(Ids::PLANKS, $magicNumber), $name . " Planks"));
			$this->register(new Sapling(new BID(Ids::SAPLING, $magicNumber), $name . " Sapling", $treeType));
			$this->register(new WoodenFence(new BID(Ids::FENCE, $magicNumber), $name . " Fence"));
			$this->register(new WoodenSlab(new BIDFlattened(Ids::WOODEN_SLAB, Ids::DOUBLE_WOODEN_SLAB, $treeType->getMagicNumber()), $treeType->getDisplayName()));

			//TODO: find a better way to deal with this split
			$this->register(new Leaves(new BID($magicNumber >= 4 ? Ids::LEAVES2 : Ids::LEAVES, $magicNumber & 0x03), $name . " Leaves", $treeType));
			$this->register(new Log(new BID($magicNumber >= 4 ? Ids::LOG2 : Ids::LOG, $magicNumber & 0x03), $name . " Log", $treeType, false));

			$wood = new Wood(new BID(Ids::WOOD, $magicNumber), $name . " Wood", $treeType, false);
			$this->register($wood);
			$this->remap($magicNumber >= 4 ? Ids::LOG2 : Ids::LOG, ($magicNumber & 0x03) | 0b1100, $wood);

			$this->register(new Log(BlockLegacyIdHelper::getStrippedLogIdentifier($treeType), "Stripped " . $treeType->getDisplayName() . " Log", $treeType, true));
			$this->register(new FenceGate(BlockLegacyIdHelper::getWoodenFenceIdentifier($treeType), $treeType->getDisplayName() . " Fence Gate"));
			$this->register(new WoodenStairs(BlockLegacyIdHelper::getWoodenStairsIdentifier($treeType), $treeType->getDisplayName() . " Stairs"));
			$this->register(new WoodenDoor(BlockLegacyIdHelper::getWoodenDoorIdentifier($treeType), $treeType->getDisplayName() . " Door"));

			$this->register(new WoodenButton(BlockLegacyIdHelper::getWoodenButtonIdentifier($treeType), $treeType->getDisplayName() . " Button"));
			$this->register(new WoodenPressurePlate(BlockLegacyIdHelper::getWoodenPressurePlateIdentifier($treeType), $treeType->getDisplayName() . " Pressure Plate"));
			$this->register(new WoodenTrapdoor(BlockLegacyIdHelper::getWoodenTrapdoorIdentifier($treeType), $treeType->getDisplayName() . " Trapdoor"));

			$this->register(new FloorSign(BlockLegacyIdHelper::getWoodenFloorSignIdentifier($treeType), $treeType->getDisplayName() . " Sign"));
			$this->register(new WallSign(BlockLegacyIdHelper::getWoodenWallSignIdentifier($treeType), $treeType->getDisplayName() . " Wall Sign"));
		}

		static $sandstoneTypes = [
			Meta::SANDSTONE_NORMAL => "",
			Meta::SANDSTONE_CHISELED => "Chiseled ",
			Meta::SANDSTONE_CUT => "Cut ",
			Meta::SANDSTONE_SMOOTH => "Smooth "
		];
		$sandstoneBreakInfo = new BlockBreakInfo(0.8, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel());
		$this->register(new Stair(new BID(Ids::RED_SANDSTONE_STAIRS, 0), "Red Sandstone Stairs", $sandstoneBreakInfo));
		$this->register(new Stair(new BID(Ids::SMOOTH_RED_SANDSTONE_STAIRS, 0), "Smooth Red Sandstone Stairs", $sandstoneBreakInfo));
		$this->register(new Stair(new BID(Ids::SANDSTONE_STAIRS, 0), "Sandstone Stairs", $sandstoneBreakInfo));
		$this->register(new Stair(new BID(Ids::SMOOTH_SANDSTONE_STAIRS, 0), "Smooth Sandstone Stairs", $sandstoneBreakInfo));
		foreach($sandstoneTypes as $variant => $prefix){
			$this->register(new Opaque(new BID(Ids::SANDSTONE, $variant), $prefix . "Sandstone", $sandstoneBreakInfo));
			$this->register(new Opaque(new BID(Ids::RED_SANDSTONE, $variant), $prefix . "Red Sandstone", $sandstoneBreakInfo));
		}

		foreach(DyeColor::getAll() as $color){
			$coloredName = function(string $name) use($color) : string{
				return $color->getDisplayName() . " " . $name;
			};
			$this->register(new GlazedTerracotta(BlockLegacyIdHelper::getGlazedTerracottaIdentifier($color), $coloredName("Glazed Terracotta")));
		}
		$this->register(new StainedGlass(new BID(Ids::STAINED_GLASS, 0), "Stained Glass"));
		$this->register(new StainedGlassPane(new BID(Ids::STAINED_GLASS_PANE, 0), "Stained Glass Pane"));
		$this->register(new StainedHardenedClay(new BID(Ids::STAINED_CLAY, 0), "Stained Clay"));
		$this->register(new StainedHardenedGlass(new BID(Ids::HARD_STAINED_GLASS, 0), "Stained Hardened Glass"));
		$this->register(new StainedHardenedGlassPane(new BID(Ids::HARD_STAINED_GLASS_PANE, 0), "Stained Hardened Glass Pane"));
		$this->register(new Carpet(new BID(Ids::CARPET, 0), "Carpet"));
		$this->register(new Concrete(new BID(Ids::CONCRETE, 0), "Concrete"));
		$this->register(new ConcretePowder(new BID(Ids::CONCRETE_POWDER, 0), "Concrete Powder"));
		$this->register(new Wool(new BID(Ids::WOOL, 0), "Wool"));

		$this->register(new Wall(new BID(Ids::COBBLESTONE_WALL, Meta::WALL_ANDESITE), "Andesite Wall"));
		$this->register(new Wall(new BID(Ids::COBBLESTONE_WALL, Meta::WALL_BRICK), "Brick Wall"));
		$this->register(new Wall(new BID(Ids::COBBLESTONE_WALL, Meta::WALL_DIORITE), "Diorite Wall"));
		$this->register(new Wall(new BID(Ids::COBBLESTONE_WALL, Meta::WALL_END_STONE_BRICK), "End Stone Brick Wall"));
		$this->register(new Wall(new BID(Ids::COBBLESTONE_WALL, Meta::WALL_GRANITE), "Granite Wall"));
		$this->register(new Wall(new BID(Ids::COBBLESTONE_WALL, Meta::WALL_MOSSY_STONE_BRICK), "Mossy Stone Brick Wall"));
		$this->register(new Wall(new BID(Ids::COBBLESTONE_WALL, Meta::WALL_MOSSY_COBBLESTONE), "Mossy Cobblestone Wall"));
		$this->register(new Wall(new BID(Ids::COBBLESTONE_WALL, Meta::WALL_NETHER_BRICK), "Nether Brick Wall"));
		$this->register(new Wall(new BID(Ids::COBBLESTONE_WALL, Meta::WALL_COBBLESTONE), "Cobblestone Wall"));
		$this->register(new Wall(new BID(Ids::COBBLESTONE_WALL, Meta::WALL_PRISMARINE), "Prismarine Wall"));
		$this->register(new Wall(new BID(Ids::COBBLESTONE_WALL, Meta::WALL_RED_NETHER_BRICK), "Red Nether Brick Wall"));
		$this->register(new Wall(new BID(Ids::COBBLESTONE_WALL, Meta::WALL_RED_SANDSTONE), "Red Sandstone Wall"));
		$this->register(new Wall(new BID(Ids::COBBLESTONE_WALL, Meta::WALL_SANDSTONE), "Sandstone Wall"));
		$this->register(new Wall(new BID(Ids::COBBLESTONE_WALL, Meta::WALL_STONE_BRICK), "Stone Brick Wall"));

		$this->registerElements();

		$chemistryTableBreakInfo = new BlockBreakInfo(2.5, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel());
		$this->register(new ChemistryTable(new BID(Ids::CHEMISTRY_TABLE, Meta::CHEMISTRY_COMPOUND_CREATOR), "Compound Creator", $chemistryTableBreakInfo));
		$this->register(new ChemistryTable(new BID(Ids::CHEMISTRY_TABLE, Meta::CHEMISTRY_ELEMENT_CONSTRUCTOR), "Element Constructor", $chemistryTableBreakInfo));
		$this->register(new ChemistryTable(new BID(Ids::CHEMISTRY_TABLE, Meta::CHEMISTRY_LAB_TABLE), "Lab Table", $chemistryTableBreakInfo));
		$this->register(new ChemistryTable(new BID(Ids::CHEMISTRY_TABLE, Meta::CHEMISTRY_MATERIAL_REDUCER), "Material Reducer", $chemistryTableBreakInfo));

		$this->register(new ChemicalHeat(new BID(Ids::CHEMICAL_HEAT, 0), "Heat Block", $chemistryTableBreakInfo));

		$this->registerMushroomBlocks();

		foreach(CoralType::getAll() as $coralType){
			$coralTypeId = CoralTypeIdMap::getInstance()->toId($coralType);
			$coralTypeName = $coralType->getDisplayName();
			$this->register(new Coral(
				new BID(Ids::CORAL, $coralTypeId),
				"$coralTypeName Coral",
				BlockBreakInfo::instant(),
				$coralType
			));
			$this->register(new FloorCoralFan(
				new BlockIdentifierFlattened(Ids::CORAL_FAN, Ids::CORAL_FAN_DEAD, $coralTypeId, ItemIds::CORAL_FAN),
				"$coralTypeName Coral Fan",
				BlockBreakInfo::instant(),
				$coralType
			));
			$this->register(new WallCoralFan(
				BlockLegacyIdHelper::getWallCoralFanIdentifier($coralType),
				"$coralTypeName Wall Coral Fan",
				BlockBreakInfo::instant(),
				$coralType
			));
		}

		//region --- auto-generated TODOs for bedrock-1.11.0 ---
		//TODO: minecraft:bell
		//TODO: minecraft:blast_furnace
		//TODO: minecraft:bubble_column
		//TODO: minecraft:campfire
		//TODO: minecraft:cartography_table
		//TODO: minecraft:cauldron
		//TODO: minecraft:chain_command_block
		//TODO: minecraft:chorus_flower
		//TODO: minecraft:chorus_plant
		//TODO: minecraft:command_block
		//TODO: minecraft:composter
		//TODO: minecraft:conduit
		//TODO: minecraft:dispenser
		//TODO: minecraft:dropper
		//TODO: minecraft:end_gateway
		//TODO: minecraft:end_portal
		//TODO: minecraft:fletching_table
		//TODO: minecraft:grindstone
		//TODO: minecraft:jigsaw
		//TODO: minecraft:kelp
		//TODO: minecraft:lava_cauldron
		//TODO: minecraft:lectern
		//TODO: minecraft:lit_blast_furnace
		//TODO: minecraft:lit_smoker
		//TODO: minecraft:loom
		//TODO: minecraft:movingBlock
		//TODO: minecraft:observer
		//TODO: minecraft:piston
		//TODO: minecraft:pistonArmCollision
		//TODO: minecraft:repeating_command_block
		//TODO: minecraft:scaffolding
		//TODO: minecraft:seagrass
		//TODO: minecraft:shulker_box
		//TODO: minecraft:slime
		//TODO: minecraft:smithing_table
		//TODO: minecraft:smoker
		//TODO: minecraft:sticky_piston
		//TODO: minecraft:stonecutter_block
		//TODO: minecraft:stripped_acacia_log
		//TODO: minecraft:stripped_birch_log
		//TODO: minecraft:stripped_dark_oak_log
		//TODO: minecraft:stripped_jungle_log
		//TODO: minecraft:stripped_oak_log
		//TODO: minecraft:stripped_spruce_log
		//TODO: minecraft:structure_block
		//TODO: minecraft:sweet_berry_bush
		//TODO: minecraft:turtle_egg
		//TODO: minecraft:undyed_shulker_box
		//endregion

		//region --- auto-generated TODOs for bedrock-1.13.0 ---
		//TODO: minecraft:camera
		//TODO: minecraft:light_block
		//TODO: minecraft:stickyPistonArmCollision
		//TODO: minecraft:structure_void
		//TODO: minecraft:wither_rose
		//endregion

		//region --- auto-generated TODOs for bedrock-1.14.0 ---
		//TODO: minecraft:bee_nest
		//TODO: minecraft:beehive
		//TODO: minecraft:honey_block
		//TODO: minecraft:honeycomb_block
		//endregion

		//region --- auto-generated TODOs for bedrock-1.16.0 ---
		//TODO: minecraft:allow
		//TODO: minecraft:ancient_debris
		//TODO: minecraft:basalt
		//TODO: minecraft:blackstone
		//TODO: minecraft:blackstone_double_slab
		//TODO: minecraft:blackstone_slab
		//TODO: minecraft:blackstone_stairs
		//TODO: minecraft:blackstone_wall
		//TODO: minecraft:border_block
		//TODO: minecraft:chain
		//TODO: minecraft:chiseled_nether_bricks
		//TODO: minecraft:chiseled_polished_blackstone
		//TODO: minecraft:cracked_nether_bricks
		//TODO: minecraft:cracked_polished_blackstone_bricks
		//TODO: minecraft:crimson_button
		//TODO: minecraft:crimson_door
		//TODO: minecraft:crimson_double_slab
		//TODO: minecraft:crimson_fence
		//TODO: minecraft:crimson_fence_gate
		//TODO: minecraft:crimson_fungus
		//TODO: minecraft:crimson_hyphae
		//TODO: minecraft:crimson_nylium
		//TODO: minecraft:crimson_planks
		//TODO: minecraft:crimson_pressure_plate
		//TODO: minecraft:crimson_roots
		//TODO: minecraft:crimson_slab
		//TODO: minecraft:crimson_stairs
		//TODO: minecraft:crimson_standing_sign
		//TODO: minecraft:crimson_stem
		//TODO: minecraft:crimson_trapdoor
		//TODO: minecraft:crimson_wall_sign
		//TODO: minecraft:crying_obsidian
		//TODO: minecraft:deny
		//TODO: minecraft:gilded_blackstone
		//TODO: minecraft:lodestone
		//TODO: minecraft:nether_gold_ore
		//TODO: minecraft:nether_sprouts
		//TODO: minecraft:netherite_block
		//TODO: minecraft:polished_basalt
		//TODO: minecraft:polished_blackstone
		//TODO: minecraft:polished_blackstone_brick_double_slab
		//TODO: minecraft:polished_blackstone_brick_slab
		//TODO: minecraft:polished_blackstone_brick_stairs
		//TODO: minecraft:polished_blackstone_brick_wall
		//TODO: minecraft:polished_blackstone_bricks
		//TODO: minecraft:polished_blackstone_button
		//TODO: minecraft:polished_blackstone_double_slab
		//TODO: minecraft:polished_blackstone_pressure_plate
		//TODO: minecraft:polished_blackstone_slab
		//TODO: minecraft:polished_blackstone_stairs
		//TODO: minecraft:polished_blackstone_wall
		//TODO: minecraft:quartz_bricks
		//TODO: minecraft:respawn_anchor
		//TODO: minecraft:shroomlight
		//TODO: minecraft:soul_campfire
		//TODO: minecraft:soul_fire
		//TODO: minecraft:soul_lantern
		//TODO: minecraft:soul_soil
		//TODO: minecraft:soul_torch
		//TODO: minecraft:stripped_crimson_hyphae
		//TODO: minecraft:stripped_crimson_stem
		//TODO: minecraft:stripped_warped_hyphae
		//TODO: minecraft:stripped_warped_stem
		//TODO: minecraft:target
		//TODO: minecraft:twisting_vines
		//TODO: minecraft:warped_button
		//TODO: minecraft:warped_door
		//TODO: minecraft:warped_double_slab
		//TODO: minecraft:warped_fence
		//TODO: minecraft:warped_fence_gate
		//TODO: minecraft:warped_fungus
		//TODO: minecraft:warped_hyphae
		//TODO: minecraft:warped_nylium
		//TODO: minecraft:warped_planks
		//TODO: minecraft:warped_pressure_plate
		//TODO: minecraft:warped_roots
		//TODO: minecraft:warped_slab
		//TODO: minecraft:warped_stairs
		//TODO: minecraft:warped_standing_sign
		//TODO: minecraft:warped_stem
		//TODO: minecraft:warped_trapdoor
		//TODO: minecraft:warped_wall_sign
		//TODO: minecraft:warped_wart_block
		//TODO: minecraft:weeping_vines
		//endregion
	}

	private function registerMushroomBlocks() : void{
		//shrooms have to be handled one by one because some metas are variants and others aren't, and they can't be
		//separated by a bitmask

		$mushroomBlockBreakInfo = new BlockBreakInfo(0.2, BlockToolType::AXE);

		$mushroomBlocks = [
			new BrownMushroomBlock(new BID(Ids::BROWN_MUSHROOM_BLOCK, 0), "Brown Mushroom Block", $mushroomBlockBreakInfo),
			new RedMushroomBlock(new BID(Ids::RED_MUSHROOM_BLOCK, 0), "Red Mushroom Block", $mushroomBlockBreakInfo)
		];

		//caps
		foreach([
			Meta::MUSHROOM_BLOCK_ALL_PORES,
			Meta::MUSHROOM_BLOCK_CAP_NORTHWEST_CORNER,
			Meta::MUSHROOM_BLOCK_CAP_NORTH_SIDE,
			Meta::MUSHROOM_BLOCK_CAP_NORTHEAST_CORNER,
			Meta::MUSHROOM_BLOCK_CAP_WEST_SIDE,
			Meta::MUSHROOM_BLOCK_CAP_TOP_ONLY,
			Meta::MUSHROOM_BLOCK_CAP_EAST_SIDE,
			Meta::MUSHROOM_BLOCK_CAP_SOUTHWEST_CORNER,
			Meta::MUSHROOM_BLOCK_CAP_SOUTH_SIDE,
			Meta::MUSHROOM_BLOCK_CAP_SOUTHEAST_CORNER,
			Meta::MUSHROOM_BLOCK_ALL_CAP,
		] as $meta){
			foreach($mushroomBlocks as $block){
				$block->readStateFromData($block->getId(), $meta);
				$this->remap($block->getId(), $meta, clone $block);
			}
		}

		//and the invalid states
		for($meta = 11; $meta <= 13; ++$meta){
			foreach($mushroomBlocks as $block){
				$this->remap($block->getId(), $meta, clone $block);
			}
		}

		//finally, the stems
		$mushroomStem = new MushroomStem(new BID(Ids::BROWN_MUSHROOM_BLOCK, Meta::MUSHROOM_BLOCK_STEM), "Mushroom Stem", $mushroomBlockBreakInfo);
		$this->remap(Ids::BROWN_MUSHROOM_BLOCK, Meta::MUSHROOM_BLOCK_STEM, $mushroomStem);
		$this->remap(Ids::RED_MUSHROOM_BLOCK, Meta::MUSHROOM_BLOCK_STEM, $mushroomStem);
		$allSidedMushroomStem = new MushroomStem(new BID(Ids::BROWN_MUSHROOM_BLOCK, Meta::MUSHROOM_BLOCK_ALL_STEM), "All Sided Mushroom Stem", $mushroomBlockBreakInfo);
		$this->remap(Ids::BROWN_MUSHROOM_BLOCK, Meta::MUSHROOM_BLOCK_ALL_STEM, $allSidedMushroomStem);
		$this->remap(Ids::RED_MUSHROOM_BLOCK, Meta::MUSHROOM_BLOCK_ALL_STEM, $allSidedMushroomStem);
	}

	private function registerElements() : void{
		$instaBreak = BlockBreakInfo::instant();
		$this->register(new Opaque(new BID(Ids::ELEMENT_0, 0), "???", $instaBreak));

		$this->register(new Element(new BID(Ids::ELEMENT_1, 0), "Hydrogen", $instaBreak, "h", 1, 5));
		$this->register(new Element(new BID(Ids::ELEMENT_2, 0), "Helium", $instaBreak, "he", 2, 7));
		$this->register(new Element(new BID(Ids::ELEMENT_3, 0), "Lithium", $instaBreak, "li", 3, 0));
		$this->register(new Element(new BID(Ids::ELEMENT_4, 0), "Beryllium", $instaBreak, "be", 4, 1));
		$this->register(new Element(new BID(Ids::ELEMENT_5, 0), "Boron", $instaBreak, "b", 5, 4));
		$this->register(new Element(new BID(Ids::ELEMENT_6, 0), "Carbon", $instaBreak, "c", 6, 5));
		$this->register(new Element(new BID(Ids::ELEMENT_7, 0), "Nitrogen", $instaBreak, "n", 7, 5));
		$this->register(new Element(new BID(Ids::ELEMENT_8, 0), "Oxygen", $instaBreak, "o", 8, 5));
		$this->register(new Element(new BID(Ids::ELEMENT_9, 0), "Fluorine", $instaBreak, "f", 9, 6));
		$this->register(new Element(new BID(Ids::ELEMENT_10, 0), "Neon", $instaBreak, "ne", 10, 7));
		$this->register(new Element(new BID(Ids::ELEMENT_11, 0), "Sodium", $instaBreak, "na", 11, 0));
		$this->register(new Element(new BID(Ids::ELEMENT_12, 0), "Magnesium", $instaBreak, "mg", 12, 1));
		$this->register(new Element(new BID(Ids::ELEMENT_13, 0), "Aluminum", $instaBreak, "al", 13, 3));
		$this->register(new Element(new BID(Ids::ELEMENT_14, 0), "Silicon", $instaBreak, "si", 14, 4));
		$this->register(new Element(new BID(Ids::ELEMENT_15, 0), "Phosphorus", $instaBreak, "p", 15, 5));
		$this->register(new Element(new BID(Ids::ELEMENT_16, 0), "Sulfur", $instaBreak, "s", 16, 5));
		$this->register(new Element(new BID(Ids::ELEMENT_17, 0), "Chlorine", $instaBreak, "cl", 17, 6));
		$this->register(new Element(new BID(Ids::ELEMENT_18, 0), "Argon", $instaBreak, "ar", 18, 7));
		$this->register(new Element(new BID(Ids::ELEMENT_19, 0), "Potassium", $instaBreak, "k", 19, 0));
		$this->register(new Element(new BID(Ids::ELEMENT_20, 0), "Calcium", $instaBreak, "ca", 20, 1));
		$this->register(new Element(new BID(Ids::ELEMENT_21, 0), "Scandium", $instaBreak, "sc", 21, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_22, 0), "Titanium", $instaBreak, "ti", 22, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_23, 0), "Vanadium", $instaBreak, "v", 23, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_24, 0), "Chromium", $instaBreak, "cr", 24, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_25, 0), "Manganese", $instaBreak, "mn", 25, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_26, 0), "Iron", $instaBreak, "fe", 26, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_27, 0), "Cobalt", $instaBreak, "co", 27, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_28, 0), "Nickel", $instaBreak, "ni", 28, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_29, 0), "Copper", $instaBreak, "cu", 29, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_30, 0), "Zinc", $instaBreak, "zn", 30, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_31, 0), "Gallium", $instaBreak, "ga", 31, 3));
		$this->register(new Element(new BID(Ids::ELEMENT_32, 0), "Germanium", $instaBreak, "ge", 32, 4));
		$this->register(new Element(new BID(Ids::ELEMENT_33, 0), "Arsenic", $instaBreak, "as", 33, 4));
		$this->register(new Element(new BID(Ids::ELEMENT_34, 0), "Selenium", $instaBreak, "se", 34, 5));
		$this->register(new Element(new BID(Ids::ELEMENT_35, 0), "Bromine", $instaBreak, "br", 35, 6));
		$this->register(new Element(new BID(Ids::ELEMENT_36, 0), "Krypton", $instaBreak, "kr", 36, 7));
		$this->register(new Element(new BID(Ids::ELEMENT_37, 0), "Rubidium", $instaBreak, "rb", 37, 0));
		$this->register(new Element(new BID(Ids::ELEMENT_38, 0), "Strontium", $instaBreak, "sr", 38, 1));
		$this->register(new Element(new BID(Ids::ELEMENT_39, 0), "Yttrium", $instaBreak, "y", 39, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_40, 0), "Zirconium", $instaBreak, "zr", 40, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_41, 0), "Niobium", $instaBreak, "nb", 41, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_42, 0), "Molybdenum", $instaBreak, "mo", 42, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_43, 0), "Technetium", $instaBreak, "tc", 43, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_44, 0), "Ruthenium", $instaBreak, "ru", 44, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_45, 0), "Rhodium", $instaBreak, "rh", 45, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_46, 0), "Palladium", $instaBreak, "pd", 46, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_47, 0), "Silver", $instaBreak, "ag", 47, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_48, 0), "Cadmium", $instaBreak, "cd", 48, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_49, 0), "Indium", $instaBreak, "in", 49, 3));
		$this->register(new Element(new BID(Ids::ELEMENT_50, 0), "Tin", $instaBreak, "sn", 50, 3));
		$this->register(new Element(new BID(Ids::ELEMENT_51, 0), "Antimony", $instaBreak, "sb", 51, 4));
		$this->register(new Element(new BID(Ids::ELEMENT_52, 0), "Tellurium", $instaBreak, "te", 52, 4));
		$this->register(new Element(new BID(Ids::ELEMENT_53, 0), "Iodine", $instaBreak, "i", 53, 6));
		$this->register(new Element(new BID(Ids::ELEMENT_54, 0), "Xenon", $instaBreak, "xe", 54, 7));
		$this->register(new Element(new BID(Ids::ELEMENT_55, 0), "Cesium", $instaBreak, "cs", 55, 0));
		$this->register(new Element(new BID(Ids::ELEMENT_56, 0), "Barium", $instaBreak, "ba", 56, 1));
		$this->register(new Element(new BID(Ids::ELEMENT_57, 0), "Lanthanum", $instaBreak, "la", 57, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_58, 0), "Cerium", $instaBreak, "ce", 58, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_59, 0), "Praseodymium", $instaBreak, "pr", 59, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_60, 0), "Neodymium", $instaBreak, "nd", 60, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_61, 0), "Promethium", $instaBreak, "pm", 61, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_62, 0), "Samarium", $instaBreak, "sm", 62, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_63, 0), "Europium", $instaBreak, "eu", 63, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_64, 0), "Gadolinium", $instaBreak, "gd", 64, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_65, 0), "Terbium", $instaBreak, "tb", 65, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_66, 0), "Dysprosium", $instaBreak, "dy", 66, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_67, 0), "Holmium", $instaBreak, "ho", 67, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_68, 0), "Erbium", $instaBreak, "er", 68, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_69, 0), "Thulium", $instaBreak, "tm", 69, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_70, 0), "Ytterbium", $instaBreak, "yb", 70, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_71, 0), "Lutetium", $instaBreak, "lu", 71, 8));
		$this->register(new Element(new BID(Ids::ELEMENT_72, 0), "Hafnium", $instaBreak, "hf", 72, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_73, 0), "Tantalum", $instaBreak, "ta", 73, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_74, 0), "Tungsten", $instaBreak, "w", 74, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_75, 0), "Rhenium", $instaBreak, "re", 75, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_76, 0), "Osmium", $instaBreak, "os", 76, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_77, 0), "Iridium", $instaBreak, "ir", 77, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_78, 0), "Platinum", $instaBreak, "pt", 78, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_79, 0), "Gold", $instaBreak, "au", 79, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_80, 0), "Mercury", $instaBreak, "hg", 80, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_81, 0), "Thallium", $instaBreak, "tl", 81, 3));
		$this->register(new Element(new BID(Ids::ELEMENT_82, 0), "Lead", $instaBreak, "pb", 82, 3));
		$this->register(new Element(new BID(Ids::ELEMENT_83, 0), "Bismuth", $instaBreak, "bi", 83, 3));
		$this->register(new Element(new BID(Ids::ELEMENT_84, 0), "Polonium", $instaBreak, "po", 84, 4));
		$this->register(new Element(new BID(Ids::ELEMENT_85, 0), "Astatine", $instaBreak, "at", 85, 6));
		$this->register(new Element(new BID(Ids::ELEMENT_86, 0), "Radon", $instaBreak, "rn", 86, 7));
		$this->register(new Element(new BID(Ids::ELEMENT_87, 0), "Francium", $instaBreak, "fr", 87, 0));
		$this->register(new Element(new BID(Ids::ELEMENT_88, 0), "Radium", $instaBreak, "ra", 88, 1));
		$this->register(new Element(new BID(Ids::ELEMENT_89, 0), "Actinium", $instaBreak, "ac", 89, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_90, 0), "Thorium", $instaBreak, "th", 90, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_91, 0), "Protactinium", $instaBreak, "pa", 91, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_92, 0), "Uranium", $instaBreak, "u", 92, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_93, 0), "Neptunium", $instaBreak, "np", 93, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_94, 0), "Plutonium", $instaBreak, "pu", 94, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_95, 0), "Americium", $instaBreak, "am", 95, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_96, 0), "Curium", $instaBreak, "cm", 96, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_97, 0), "Berkelium", $instaBreak, "bk", 97, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_98, 0), "Californium", $instaBreak, "cf", 98, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_99, 0), "Einsteinium", $instaBreak, "es", 99, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_100, 0), "Fermium", $instaBreak, "fm", 100, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_101, 0), "Mendelevium", $instaBreak, "md", 101, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_102, 0), "Nobelium", $instaBreak, "no", 102, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_103, 0), "Lawrencium", $instaBreak, "lr", 103, 9));
		$this->register(new Element(new BID(Ids::ELEMENT_104, 0), "Rutherfordium", $instaBreak, "rf", 104, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_105, 0), "Dubnium", $instaBreak, "db", 105, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_106, 0), "Seaborgium", $instaBreak, "sg", 106, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_107, 0), "Bohrium", $instaBreak, "bh", 107, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_108, 0), "Hassium", $instaBreak, "hs", 108, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_109, 0), "Meitnerium", $instaBreak, "mt", 109, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_110, 0), "Darmstadtium", $instaBreak, "ds", 110, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_111, 0), "Roentgenium", $instaBreak, "rg", 111, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_112, 0), "Copernicium", $instaBreak, "cn", 112, 2));
		$this->register(new Element(new BID(Ids::ELEMENT_113, 0), "Nihonium", $instaBreak, "nh", 113, 3));
		$this->register(new Element(new BID(Ids::ELEMENT_114, 0), "Flerovium", $instaBreak, "fl", 114, 3));
		$this->register(new Element(new BID(Ids::ELEMENT_115, 0), "Moscovium", $instaBreak, "mc", 115, 3));
		$this->register(new Element(new BID(Ids::ELEMENT_116, 0), "Livermorium", $instaBreak, "lv", 116, 3));
		$this->register(new Element(new BID(Ids::ELEMENT_117, 0), "Tennessine", $instaBreak, "ts", 117, 6));
		$this->register(new Element(new BID(Ids::ELEMENT_118, 0), "Oganesson", $instaBreak, "og", 118, 7));
	}

	/**
	 * Maps a block type to its corresponding ID. This is necessary to ensure that the block is correctly loaded when
	 * reading from disk storage.
	 *
	 * NOTE: If you are registering a new block type, you will need to add it to the creative inventory yourself - it
	 * will not automatically appear there.
	 *
	 * @param bool  $override Whether to override existing registrations
	 *
	 * @throws \RuntimeException if something attempted to override an already-registered block without specifying the
	 * $override parameter.
	 */
	public function register(Block $block, bool $override = false) : void{
		$variant = $block->getIdInfo()->getVariant();

		$stateMask = $block->getStateBitmask();
		if(($variant & $stateMask) !== 0){
			throw new \InvalidArgumentException("Block variant collides with state bitmask");
		}

		foreach($block->getIdInfo()->getAllBlockIds() as $id){
			if(!$override and $this->isRegistered($id, $variant)){
				throw new \InvalidArgumentException("Block registration $id:$variant conflicts with an existing block");
			}

			for($m = $variant; $m <= ($variant | $stateMask); ++$m){
				if(($m & ~$stateMask) !== $variant){
					continue;
				}

				if(!$override and $this->isRegistered($id, $m)){
					throw new \InvalidArgumentException("Block registration " . get_class($block) . " has states which conflict with other blocks");
				}

				$index = ($id << 4) | $m;

				$v = clone $block;
				try{
					$v->readStateFromData($id, $m);
					if($v->getMeta() !== $m){
						throw new InvalidBlockStateException("Corrupted meta"); //don't register anything that isn't the same when we read it back again
					}
				}catch(InvalidBlockStateException $e){ //invalid property combination
					continue;
				}

				$this->fillStaticArrays($index, $v);
			}

			if(!$this->isRegistered($id, $variant)){
				$this->fillStaticArrays(($id << 4) | $variant, $block); //register default state mapped to variant, for blocks which don't use 0 as valid state
			}
		}
	}

	public function remap(int $id, int $meta, Block $block) : void{
		if($this->isRegistered($id, $meta)){
			throw new \InvalidArgumentException("$id:$meta is already mapped");
		}
		$this->fillStaticArrays(($id << 4) | $meta, $block);
	}

	private function fillStaticArrays(int $index, Block $block) : void{
		$this->fullList[$index] = $block;
		$this->light[$index] = $block->getLightLevel();
		$this->lightFilter[$index] = min(15, $block->getLightFilter() + 1); //opacity plus 1 standard light filter
		$this->blocksDirectSkyLight[$index] = $block->blocksDirectSkyLight();
		$this->blastResistance[$index] = $block->getBreakInfo()->getBlastResistance();
	}

	/**
	 * @deprecated This method should ONLY be used for deserializing data, e.g. from a config or database. For all other
	 * purposes, use VanillaBlocks.
	 * @see VanillaBlocks
	 *
	 * Deserializes a block from the provided legacy ID and legacy meta.
	 */
	public function get(int $id, int $meta) : Block{
		if($meta < 0 or $meta > 0xf){
			throw new \InvalidArgumentException("Block meta value $meta is out of bounds");
		}

		/** @var Block|null $block */
		$block = null;
		try{
			$index = ($id << 4) | $meta;
			if($this->fullList[$index] !== null){
				$block = clone $this->fullList[$index];
			}
		}catch(\RuntimeException $e){
			throw new \InvalidArgumentException("Block ID $id is out of bounds");
		}

		if($block === null){
			$block = new UnknownBlock(new BID($id, $meta));
		}

		return $block;
	}

	public function fromFullBlock(int $fullState) : Block{
		return $this->get($fullState >> 4, $fullState & 0xf);
	}

	/**
	 * Returns whether a specified block state is already registered in the block factory.
	 */
	public function isRegistered(int $id, int $meta = 0) : bool{
		$b = $this->fullList[($id << 4) | $meta];
		return $b !== null and !($b instanceof UnknownBlock);
	}

	/**
	 * @return Block[]
	 */
	public function getAllKnownStates() : array{
		return array_filter($this->fullList->toArray(), function(?Block $v) : bool{ return $v !== null; });
	}
}

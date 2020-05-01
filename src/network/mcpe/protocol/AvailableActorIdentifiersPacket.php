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

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\nbt\NbtDataException;
use pocketmine\network\mcpe\protocol\serializer\NetworkBinaryStream;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;

class AvailableActorIdentifiersPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::AVAILABLE_ACTOR_IDENTIFIERS_PACKET;

	/**
	 * @var CacheableNbt
	 * @phpstan-var CacheableNbt<\pocketmine\nbt\tag\CompoundTag>
	 */
	public $identifiers;

	/**
	 * @phpstan-param CacheableNbt<\pocketmine\nbt\tag\CompoundTag> $nbt
	 */
	public static function create(CacheableNbt $nbt) : self{
		$result = new self;
		$result->identifiers = $nbt;
		return $result;
	}

	protected function decodePayload(NetworkBinaryStream $in) : void{
		$offset = $in->getOffset();
		try{
			$this->identifiers = new CacheableNbt((new NetworkNbtSerializer())->read($in->getBuffer(), $offset)->mustGetCompoundTag());
		}catch(NbtDataException $e){
			throw PacketDecodeException::wrap($e, "Failed decoding actor identifiers");
		}
		$in->setOffset($offset);
	}

	protected function encodePayload(NetworkBinaryStream $out) : void{
		$out->put($this->identifiers->getEncodedNbt());
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleAvailableActorIdentifiers($this);
	}
}
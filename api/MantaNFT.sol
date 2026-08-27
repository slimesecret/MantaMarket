// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC721/extensions/ERC721URIStorage.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract MantaNFT is ERC721URIStorage, Ownable {
    uint256 private _tokenId;

    constructor() ERC721("Manta NFT", "MNFT") Ownable(msg.sender) {}

    function mint(address to, string memory tokenURI) external onlyOwner returns (uint256) {
        uint256 id = _tokenId++;
        _mint(to, id);
        _setTokenURI(id, tokenURI);
        return id;
    }

    function mintForBuyer(string memory tokenURI) external returns (uint256) {
        uint256 id = _tokenId++;
        _mint(msg.sender, id);
        _setTokenURI(id, tokenURI);
        return id;
    }
}
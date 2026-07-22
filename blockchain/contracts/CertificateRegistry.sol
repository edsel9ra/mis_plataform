// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

contract CertificateRegistry {
    address public admin;
    uint256 public certificateCount;

    struct Certificate {
        bytes32 certificateHash;
        address issuer;
        uint256 issuedAt;
        bool revoked;
    }

    mapping(bytes32 => Certificate) public certificates;
    mapping(address => bool) public issuers;

    event CertificateIssued(bytes32 indexed certificateHash, address indexed issuer, uint256 timestamp);
    event CertificateRevoked(bytes32 indexed certificateHash, address indexed issuer, uint256 timestamp);
    event IssuerAdded(address indexed issuer);
    event IssuerRemoved(address indexed issuer);

    modifier onlyAdmin() {
        require(msg.sender == admin, "Only admin can call this function");
        _;
    }

    modifier onlyIssuer() {
        require(issuers[msg.sender] || msg.sender == admin, "Only authorized issuers");
        _;
    }

    constructor() {
        admin = msg.sender;
        issuers[msg.sender] = true;
    }

    function addIssuer(address _issuer) external onlyAdmin {
        require(_issuer != address(0), "Invalid address");
        issuers[_issuer] = true;
        emit IssuerAdded(_issuer);
    }

    function removeIssuer(address _issuer) external onlyAdmin {
        require(_issuer != admin, "Cannot remove admin");
        issuers[_issuer] = false;
        emit IssuerRemoved(_issuer);
    }

    function issue(bytes32 _certificateHash) external onlyIssuer {
        require(_certificateHash != bytes32(0), "Invalid hash");
        require(certificates[_certificateHash].issuedAt == 0, "Certificate already exists");

        certificates[_certificateHash] = Certificate({
            certificateHash: _certificateHash,
            issuer: msg.sender,
            issuedAt: block.timestamp,
            revoked: false
        });

        certificateCount++;

        emit CertificateIssued(_certificateHash, msg.sender, block.timestamp);
    }

    function verify(bytes32 _certificateHash) external view returns (bool) {
        Certificate memory cert = certificates[_certificateHash];
        return cert.issuedAt > 0 && !cert.revoked;
    }

    function revoke(bytes32 _certificateHash) external onlyIssuer {
        require(certificates[_certificateHash].issuedAt > 0, "Certificate does not exist");
        require(!certificates[_certificateHash].revoked, "Certificate already revoked");

        certificates[_certificateHash].revoked = true;

        emit CertificateRevoked(_certificateHash, msg.sender, block.timestamp);
    }

    function getCertificate(bytes32 _certificateHash)
        external
        view
        returns (address issuer, uint256 issuedAt, bool revoked)
    {
        Certificate memory cert = certificates[_certificateHash];
        return (cert.issuer, cert.issuedAt, cert.revoked);
    }

    function transferAdmin(address _newAdmin) external onlyAdmin {
        require(_newAdmin != address(0), "Invalid address");
        issuers[_newAdmin] = true;
        issuers[admin] = true;
        admin = _newAdmin;
    }
}

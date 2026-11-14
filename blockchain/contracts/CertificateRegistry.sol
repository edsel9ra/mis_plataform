// SPDX-License-Identifier: MIT
pragma solidity 0.8.24;

contract CertificateRegistry {
    address public admin;
    address public pendingAdmin;
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
    event AdminTransferred(address indexed previousAdmin, address indexed newAdmin);
    event AdminTransferStarted(address indexed previousAdmin, address indexed newAdmin);
    event AdminTransferCanceled(address indexed admin, address indexed canceledAdmin);

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
        require(msg.sender == admin || certificates[_certificateHash].issuer == msg.sender, "Only admin or original issuer");

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

    /// @notice Starts a two-step admin transfer; the candidate must call acceptAdmin() to complete it.
    function transferAdmin(address _newAdmin) external onlyAdmin {
        require(_newAdmin != address(0), "Invalid address");
        require(_newAdmin != admin, "New admin is already admin");
        pendingAdmin = _newAdmin;
        emit AdminTransferStarted(admin, _newAdmin);
    }

    /// @notice Completes the admin transfer; can only be called by the pending admin.
    function acceptAdmin() external {
        require(msg.sender == pendingAdmin, "Only pending admin");
        address previousAdmin = admin;
        issuers[previousAdmin] = false;
        issuers[pendingAdmin] = true;
        admin = pendingAdmin;
        pendingAdmin = address(0);
        emit AdminTransferred(previousAdmin, admin);
    }

    /// @notice Cancels a pending admin transfer; reverts when no transfer is pending.
    function cancelAdminTransfer() external onlyAdmin {
        require(pendingAdmin != address(0), "No pending transfer");
        address canceledAdmin = pendingAdmin;
        pendingAdmin = address(0);
        emit AdminTransferCanceled(admin, canceledAdmin);
    }
}

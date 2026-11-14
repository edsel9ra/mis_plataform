// SPDX-License-Identifier: MIT
pragma solidity 0.8.24;

import {Test} from "forge-std/Test.sol";
import {CertificateRegistry} from "../contracts/CertificateRegistry.sol";

contract CertificateRegistryTest is Test {
    CertificateRegistry public registry;

    address public admin = address(0xA);
    address public issuer = address(0xB);
    address public otherIssuer = address(0xC);
    address public newAdmin = address(0xD);
    address public stranger = address(0xE);

    bytes32 public constant HASH = keccak256("certificate-1");

    event CertificateIssued(bytes32 indexed certificateHash, address indexed issuer, uint256 timestamp);
    event CertificateRevoked(bytes32 indexed certificateHash, address indexed issuer, uint256 timestamp);
    event IssuerAdded(address indexed issuer);
    event IssuerRemoved(address indexed issuer);
    event AdminTransferred(address indexed previousAdmin, address indexed newAdmin);
    event AdminTransferStarted(address indexed previousAdmin, address indexed newAdmin);
    event AdminTransferCanceled(address indexed admin, address indexed canceledAdmin);

    function setUp() public {
        vm.prank(admin);
        registry = new CertificateRegistry();
    }

    function testIssueVerifyGetCertificate() public {
        vm.prank(issuer);
        vm.expectRevert("Only authorized issuers");
        registry.issue(HASH);

        vm.prank(admin);
        registry.addIssuer(issuer);

        vm.expectEmit(true, true, false, true);
        emit CertificateIssued(HASH, issuer, block.timestamp);
        vm.prank(issuer);
        registry.issue(HASH);

        assertTrue(registry.verify(HASH));
        (address certIssuer, uint256 issuedAt, bool revoked) = registry.getCertificate(HASH);
        assertEq(certIssuer, issuer);
        assertGt(issuedAt, 0);
        assertFalse(revoked);
        assertEq(registry.certificateCount(), 1);
    }

    function testIssueRevertsOnZeroHashAndDuplicate() public {
        vm.prank(admin);
        vm.expectRevert("Invalid hash");
        registry.issue(bytes32(0));

        vm.prank(admin);
        registry.issue(HASH);

        vm.prank(admin);
        vm.expectRevert("Certificate already exists");
        registry.issue(HASH);
    }

    function testOnlyIssuersCanIssue() public {
        vm.prank(stranger);
        vm.expectRevert("Only authorized issuers");
        registry.issue(HASH);
    }

    function testRevokeByOriginalIssuerAndAdmin() public {
        vm.prank(admin);
        registry.addIssuer(issuer);
        vm.prank(admin);
        registry.addIssuer(otherIssuer);

        vm.prank(issuer);
        registry.issue(HASH);

        // another issuer cannot revoke
        vm.prank(otherIssuer);
        vm.expectRevert("Only admin or original issuer");
        registry.revoke(HASH);

        // original issuer can
        vm.expectEmit(true, true, false, true);
        emit CertificateRevoked(HASH, issuer, block.timestamp);
        vm.prank(issuer);
        registry.revoke(HASH);
        assertFalse(registry.verify(HASH));

        // admin can revoke a different certificate
        bytes32 hash2 = keccak256("certificate-2");
        vm.prank(otherIssuer);
        registry.issue(hash2);
        vm.prank(admin);
        registry.revoke(hash2);
        assertFalse(registry.verify(hash2));
    }

    function testIssuerManagementOnlyAdmin() public {
        vm.prank(stranger);
        vm.expectRevert("Only admin can call this function");
        registry.addIssuer(issuer);

        vm.prank(admin);
        vm.expectEmit(true, false, false, true);
        emit IssuerAdded(issuer);
        registry.addIssuer(issuer);
        assertTrue(registry.issuers(issuer));

        vm.prank(admin);
        vm.expectRevert("Cannot remove admin");
        registry.removeIssuer(admin);

        vm.prank(stranger);
        vm.expectRevert("Only admin can call this function");
        registry.removeIssuer(issuer);

        vm.prank(admin);
        vm.expectEmit(true, false, false, true);
        emit IssuerRemoved(issuer);
        registry.removeIssuer(issuer);
        assertFalse(registry.issuers(issuer));
    }

    function testTwoStepAdminTransfer() public {
        // only admin can start
        vm.prank(stranger);
        vm.expectRevert("Only admin can call this function");
        registry.transferAdmin(newAdmin);

        // rejects zero address
        vm.prank(admin);
        vm.expectRevert("Invalid address");
        registry.transferAdmin(address(0));

        // rejects current admin
        vm.prank(admin);
        vm.expectRevert("New admin is already admin");
        registry.transferAdmin(admin);

        vm.prank(admin);
        vm.expectEmit(true, true, false, true);
        emit AdminTransferStarted(admin, newAdmin);
        registry.transferAdmin(newAdmin);
        assertEq(registry.pendingAdmin(), newAdmin);
        assertEq(registry.admin(), admin);

        // only pendingAdmin can accept
        vm.prank(stranger);
        vm.expectRevert("Only pending admin");
        registry.acceptAdmin();

        vm.prank(newAdmin);
        vm.expectEmit(true, true, false, true);
        emit AdminTransferred(admin, newAdmin);
        registry.acceptAdmin();

        assertEq(registry.admin(), newAdmin);
        assertEq(registry.pendingAdmin(), address(0));
        assertFalse(registry.issuers(admin));
        assertTrue(registry.issuers(newAdmin));
    }

    function testCancelAdminTransfer() public {
        vm.prank(admin);
        registry.transferAdmin(newAdmin);

        vm.prank(stranger);
        vm.expectRevert("Only admin can call this function");
        registry.cancelAdminTransfer();

        vm.expectEmit(true, true, false, true);
        emit AdminTransferCanceled(admin, newAdmin);
        vm.prank(admin);
        registry.cancelAdminTransfer();
        assertEq(registry.pendingAdmin(), address(0));

        vm.prank(newAdmin);
        vm.expectRevert("Only pending admin");
        registry.acceptAdmin();
        assertEq(registry.admin(), admin);

        vm.prank(admin);
        vm.expectRevert("No pending transfer");
        registry.cancelAdminTransfer();
    }
}

<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Storage;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

/**
 * Storage interface for file operations in cloud storage (e.g., S3).
 *
 * All methods may throw StorageException on failure unless otherwise noted.
 */
interface StorageInterface
{
    /**
     * Store a stream to the specified path.
     *
     * @param string $path The storage path (e.g., 'uploads/file.pdf')
     * @param StreamInterface $stream The content stream to store
     *
     * @throws StorageException If the upload fails
     * @throws InvalidArgumentException If the path is empty or invalid
     */
    public function put(string $path, StreamInterface $stream): void;

    /**
     * Retrieve a file as a stream from the specified path.
     *
     * @param string $path The storage path (e.g., 'uploads/file.pdf')
     *
     * @return StreamInterface The file content as a stream
     *
     * @throws StorageException If the file does not exist or retrieval fails
     * @throws InvalidArgumentException If the path is empty or invalid
     */
    public function get(string $path): StreamInterface;

    /**
     * Store a string to the specified path.
     *
     * Useful for storing text content, CSV data, or small files.
     *
     * @param string $path The storage path (e.g., 'backup/data.csv')
     * @param string $content The content to store
     *
     * @throws StorageException If the upload fails
     * @throws InvalidArgumentException If the path is empty or invalid
     */
    public function putString(string $path, string $content): void;

    /**
     * Retrieve a file as a string from the specified path.
     *
     * Useful for retrieving text files, CSV data, or small files.
     *
     * @param string $path The storage path (e.g., 'backup/data.csv')
     *
     * @return string The file content as a string
     *
     * @throws StorageException If the file does not exist or retrieval fails
     * @throws InvalidArgumentException If the path is empty or invalid
     */
    public function getString(string $path): string;

    /**
     * Upload a local file to the specified storage path.
     *
     * @param string $path The storage path (e.g., 'uploads/image.jpg')
     * @param string $filePath The absolute path to the local file to upload
     *
     * @throws StorageException If the upload fails or source file does not exist
     * @throws InvalidArgumentException If the path is empty or invalid
     */
    public function putFile(string $path, string $filePath): void;

    /**
     * Delete a file from storage.
     *
     * Note: This method throws an exception if the file does not exist.
     * Consider wrapping in try-catch if the file may already be deleted.
     *
     * @param string $path The storage path (e.g., 'uploads/file.pdf')
     *
     * @throws StorageException If the deletion fails or file does not exist
     * @throws InvalidArgumentException If the path is empty or invalid
     */
    public function delete(string $path): void;

    /**
     * Check if a file exists in storage.
     *
     * @param string $path The storage path (e.g., 'uploads/file.pdf')
     *
     * @return bool True if the file exists, false otherwise
     *
     * @throws InvalidArgumentException If the path is empty or invalid
     */
    public function exists(string $path): bool;

    /**
     * Get the size of a file in bytes.
     *
     * @param string $path The storage path (e.g., 'uploads/file.pdf')
     *
     * @return int The file size in bytes
     *
     * @throws StorageException If the file does not exist or size cannot be determined
     * @throws InvalidArgumentException If the path is empty or invalid
     */
    public function getSize(string $path): int;

    /**
     * Copy a file from one path to another within storage.
     *
     * @param string $from The source storage path (e.g., 'uploads/old.pdf')
     * @param string $to The destination storage path (e.g., 'uploads/new.pdf')
     *
     * @throws StorageException If the source file does not exist or copy fails
     * @throws InvalidArgumentException If either path is empty or invalid
     */
    public function copy(string $from, string $to): void;
}

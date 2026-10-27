<?php

/*
 * This file is part of Fast Excel.
 *
 * (c) Raphaël Huchet (rap2h, rap2hpoutre)
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Laravel\Nova\Actions\Responses;

use DateTimeInterface;
use Generator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV;
use OpenSpout\Writer\CSV\Options as CsvWriterOptions;
use OpenSpout\Writer\ODS;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamExportableCsv
{
    /**
     * The row number to start writing from. Default is 1 (first row).
     */
    protected int $startRow = 1;

    /**
     * The options for CSV export. Supported options are:
     * - delimiter: The field delimiter (one character only). Default is ','.
     * - enclosure: The field enclosure character (one character only). Default is '"'.
     * - encoding: The character encoding of the output file. Default is 'UTF-8
     * - bom: Whether to add a Byte Order Mark (BOM) at the beginning of the file. Default is true.
     *
     * @var array{delimiter: string, enclosure: string, encoding: string, bom: bool}
     */
    protected $options = [
        'delimiter' => ',',
        'enclosure' => '"',
        'encoding' => 'UTF-8',
        'bom' => true,
    ];

    /**
     * Create a new StreamExportableCsv instance.
     */
    public function __construct(
        public array|Generator|Collection $data
    ) {
        //
    }

    /**
     * Export the CSV file to the given path.
     *
     * @throws \OpenSpout\Common\Exception\InvalidArgumentException
     * @throws \OpenSpout\Common\Exception\UnsupportedTypeException
     * @throws \OpenSpout\Writer\Exception\WriterNotOpenedException
     * @throws \OpenSpout\Common\Exception\IOException
     */
    public function export(string $path, ?callable $callback = null): string
    {
        $this->exportOrDownload($path, 'openToFile', $callback);

        return realpath($path) ?: $path;
    }

    /**
     * Download the CSV file to the browser.
     *
     * @throws \OpenSpout\Common\Exception\InvalidArgumentException
     * @throws \OpenSpout\Common\Exception\UnsupportedTypeException
     * @throws \OpenSpout\Writer\Exception\WriterNotOpenedException
     * @throws \OpenSpout\Common\Exception\IOException
     */
    public function download(string $path, ?callable $callback = null): StreamedResponse
    {
        return response()->streamDownload(function () use ($path, $callback) {
            $this->exportOrDownload($path, 'openToBrowser', $callback);
        }, $path);
    }

    /**
     * Export the CSV file to the browser.
     *
     * @param  'openToBrowser'|'openToFile'  $method
     *
     * @throws \InvalidArgumentException
     */
    protected function exportOrDownload(string $path, string $method, ?callable $callback = null): void
    {
        if (str_ends_with($path, 'csv')) {
            $options = new CSV\Options;
            $writer = new CSV\Writer($options);
        } elseif (str_ends_with($path, 'ods')) {
            $options = new ODS\Options;
            $writer = new ODS\Writer($options);
        } else {
            $options = new XLSX\Options;
            $writer = new XLSX\Writer($options);
        }

        if ($options instanceof CsvWriterOptions) {
            $options->FIELD_DELIMITER = $this->options['delimiter'];
            $options->FIELD_ENCLOSURE = $this->options['enclosure'];
            $options->SHOULD_ADD_BOM = $this->options['bom'];
        }

        /* @var \OpenSpout\Writer\WriterInterface $writer */
        $writer->{$method}($path);

        $hasSheets = ($writer instanceof XLSX\Writer || $writer instanceof ODS\Writer);

        $data = $this->data instanceof Collection
            ? $this->data
            : new Collection([$this->data]);

        foreach ($data as $key => $collection) {
            if ($collection instanceof Collection) {
                $this->writeRowsFromCollection($writer, $collection, $callback);
            } elseif ($collection instanceof Generator) {
                $this->writeRowsFromGenerator($writer, $collection, $callback);
            } elseif (\is_array($collection)) {
                $this->writeRowsFromArray($writer, $collection, $callback);
            } else {
                throw new InvalidArgumentException('Unsupported type for $data');
            }

            if (\is_string($key)) {
                $writer->getCurrentSheet()->setName($key);
            }

            if ($hasSheets && $data->keys()->last() !== $key) {
                $writer->addNewSheetAndMakeItCurrent();
            }
        }

        $writer->close();
    }

    /**
     * Write rows from a collection to the writer.
     */
    protected function writeRowsFromCollection(WriterInterface $writer, Collection $collection, ?callable $callback = null): void
    {
        if (\is_callable($callback)) {
            $collection->transform(fn ($value) => $callback($value));
        }

        $this->prepareCollection($collection);

        $this->writeHeader($writer, $collection->first());

        if (! \is_array($collection->first())) {
            $collection = $collection->map(fn ($value) => $value->toArray());
        }

        $rows = $collection->map(fn ($value) => Row::fromValues($value))->toArray();

        $writer->addRows($rows);
    }

    /**
     * Write rows from a generator to the writer. This is useful for large datasets that cannot be loaded into memory at once.
     */
    protected function writeRowsFromGenerator(WriterInterface $writer, Generator $generator, ?callable $callback = null): void
    {
        foreach ($generator as $key => $item) {
            // Apply callback
            if (\is_callable($callback)) {
                $item = $callback($item);
            }

            $item = $this->transformRow($item);

            // Add header row.
            if ($key === 0) {
                $this->writeHeader($writer, $item);
            }

            // Write rows (one by one).
            $writer->addRow(Row::fromValues($item->toArray()));
        }
    }

    /**
     * Write rows from an array to the writer.
     */
    protected function writeRowsFromArray(WriterInterface $writer, array $array, ?callable $callback = null): void
    {
        $collection = new Collection($array);

        if (\is_object($collection->first()) || \is_array($collection->first())) {
            // provided $array was valid and could be converted to a collection
            $this->writeRowsFromCollection($writer, $collection, $callback);
        }
    }

    /**
     * Write the header row to the writer.
     */
    protected function writeHeader(WriterInterface $writer, Collection|array|null $firstRow): void
    {
        if ($firstRow === null) {
            return;
        }

        $keys = array_keys(\is_array($firstRow) ? $firstRow : $firstRow->toArray());

        $writer->addRow(Row::fromValues($keys));
    }

    /**
     * Prepare collection by removing non string if required.
     */
    protected function prepareCollection(Collection $collection): void
    {
        $needConversion = false;
        $firstRow = $collection->first();

        if (\is_null($firstRow)) {
            return;
        }

        foreach ($firstRow as $item) {
            if (! \is_string($item)) {
                $needConversion = true;
            }
        }

        if ($needConversion) {
            $collection->transform(fn ($data) => $this->transformRow($data));
        }
    }

    /**
     * Transform one row (i.e remove non-string).
     */
    protected function transformRow(Arrayable|iterable $data): Collection
    {
        return (new Collection($data))
            ->map(fn ($value) => \is_null($value) ? (string) $value : $value)
            ->filter(fn ($value) => \is_string($value) || \is_int($value) || \is_float($value) || $value instanceof DateTimeInterface);
    }
}

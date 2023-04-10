<?php
class Converter implements IConverter
{
    private array $rows = [];

    public function __construct()
    {
    }

    public function convert(string $input, string $output): array
    {
        if (!file_exists($input)) {
            throw new FileNotFoundException();
        }

        $fileContents = file_get_contents($input);
        $transactions = $this->extractTransactions($fileContents);
        $this->rows = $this->convertToRows($transactions);
        return $this->rows;
    }

    private function extractTransactions(string $fileContents): array
    {
        preg_match_all('/(?<=:61:).*?(?=:[0-9]{2}[A-Z]{0,1}:)|(?<=:86:).*?(?=:[0-9]{2}[A-Z]{0,1}:)/s', $fileContents, $matches);
        return $matches[0];
    }

    private function convertToRows(array $transactions): array
    {
        $rows = [];
        foreach (array_chunk($transactions, 2) as [$transaction, $description]) {
            $row = $this->convertToRow($transaction, $description);
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function convertToRow(string $transaction, string $description): Transaction
    {
        preg_match('/(\d{6})(\d{4})?([A-Z])([A-Z]{1,2})?(\d+,\d+)?/', $transaction, $matches);
        if (sizeof($matches) !== 6) {
            echo "Error in parsing line " . $transaction;
            return null;
        }

        $transactionDate = $matches[1];
        $date = DateTime::createFromFormat('ymd', $transactionDate);

        $transactionAmount = str_replace(',', '.', $matches[5]);
        if ($matches[3] === 'D') {
            $transactionAmount = -$transactionAmount;
        }

        $iban = null;
        $cleanedDescription = preg_replace('/\?[0-9]{2}/', '', $description);
        $cleanedDescription = preg_replace('/TAN: (\d{6})/', 'TAN: xxxxxx', $cleanedDescription);
        preg_match('/IBAN: ([A-Z]{2}\d{2}[A-Z0-9]{14})/', $cleanedDescription, $ibanMatches);
        if (sizeof($ibanMatches) === 2) {
            $iban = $ibanMatches[1];
        }

        return new Transaction($date->format('Y-m-d'), $transactionAmount, $iban, $cleanedDescription);
    }
}

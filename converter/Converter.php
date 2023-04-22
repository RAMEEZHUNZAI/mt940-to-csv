<?php
/**
 * Converter implementation
 */
class Converter implements IConverter
{
    private array $rows = [];

    private IConverterHelper $converterHelper;

    public function __construct()
    {
        $this->converterHelper = new ConverterHelper();
    }

    
    /**
     * Convert the input MT940 file to a list of transactions.
     *
     * @param  string $input The path to the input file name
     * @return array The list of transactions
     */
    public function convert(string $input): array
    {
        if (!file_exists($input)) {
            throw new FileNotFoundException();
        }

        $fileContents = file_get_contents($input);
        $transactions = $this->extractTransactions($fileContents);
        $this->rows = $this->convertToRows($transactions);
        return $this->rows;
    }

    /**
     * Extract transactions from the given file contents using regular expressions.
     *
     * @param string $fileContents The contents of the input file
     * @return array The list of transactions
     */
    private function extractTransactions(string $fileContents): array
    {
        preg_match_all('/(?<=:61:).*?(?=:[0-9]{2}[A-Z]{0,1}:)|(?<=:86:).*?(?=:[0-9]{2}[A-Z]{0,1}:)/s', $fileContents, $matches);
        return $matches[0];
    }

    /**
     * Convert a list of transactions to rows.
     *
     * @param array $transactions The list of transactions
     * @return array The list of rows
     */
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

    /**
     * Convert a transaction to a row.
     *
     * @param string $transaction The transaction to convert
     * @param string $description The description of the transaction
     * @return Transaction|null The converted row or null if an error occurred
     */
    private function convertToRow(string $transaction, string $description): ?Transaction
    {
        preg_match('/(\d{6})(\d{4})?([A-Z])([A-Z]{1,2})?(\d+,\d+)?/', $transaction, $matches);
        if (sizeof($matches) !== 6) {
            echo "Error in parsing line " . $transaction;
            return null;
        }

        $trx = new Transaction();

        $transactionDate = $matches[1];
        $date = DateTime::createFromFormat('ymd', $transactionDate);

        $cleanedDescription = $this->converterHelper->cleanDescription($description);

        $iban = $this->converterHelper->getIBAN($cleanedDescription);
        $name = $this->converterHelper->getName($cleanedDescription);

        $memo = $this->converterHelper->getMemo($cleanedDescription);
        $sepa = $this->converterHelper->getSEPAMandateReference($memo); 

        $trx->transactionDate = $date;
        $trx->description = rtrim($memo);
        $trx->sepaReference = $sepa;

        $transactionAmount = str_replace(',', '.', $matches[5]);
        $type = $matches[3];
        if ($type === 'D') {
            $trx->transactionAmount = floatval(-$transactionAmount);
            $trx->recepientIban = $iban;
            $trx->recipientName = $name;
        }else{
            $trx->transactionAmount = floatval($transactionAmount);
            $trx->payerIban = $iban;
            $trx->payerName = $name;
        }

        return $trx;
    }
}

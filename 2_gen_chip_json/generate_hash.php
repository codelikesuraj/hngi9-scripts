<?php

$display_sample = true;
$file_name = '';
$expected_columns = [
    'team names',
    'series number',
    'filename',
    'name',
    'description',
    'gender',
    'attributes',
    'uuid'
];
$output_rows = [];

// get filename
if (!array_key_exists(1, $argv)) {
    die('Enter file name as argument e.g "php load_csv.php filename.csv"');
}

$file_name = trim($argv[1]);
$output_file_name = substr($file_name, 0, strrpos($file_name, '.csv')) . '.output.csv';

// check if the file is valid
if (!file_is_valid($file_name)) {
    die('Error: file not found');
}

// check if the file is csv
if (!file_is_csv($file_name)) {
    die('Error: enter a valid csv file');
}

// check if the required columns exist
if (($columns = file_has_columns($file_name, $expected_columns)) !== true) {
    die('Error: missing column(s) - ( \'' . implode("', '", $columns) . '\' )');
}

// fetch all rows
$rows = get_rows($file_name);

if (!count($rows)) {
    die('Error: No row(s) found');
}

// fetch rows with actual data
$valid_rows = num_of_valid_rows($rows, $expected_columns);

if ($valid_rows < 1) {
    die('Error: No valid row(s) found');
}

// generate hash for each row
for ($i = 0; $i < count($rows); $i++) {
    $row = $rows[$i];
    $output_rows[] = $row;
    $attributes = [];

    if ($i === 0) {
        $output_rows[$i][] = 'Hash';
    } else {
        $output_rows[$i][] = '';
    }

    if (row_is_valid($row, $expected_columns)) {
        $attribute = get_row_attributes($row[6]);
        $attributes[] = [
            'trait_type' => 'Gender',
            'value' => $row[5]
        ];

        foreach ($attribute as $trait_type => $value) {
            $attributes[] = [
                'trait_type' => $trait_type,
                'value' => $value
            ];
        }

        $temp_attributes = $attributes;
        array_shift($temp_attributes);
        $output_attribute = [];
        foreach ($temp_attributes as $single_attribute) {
            $output_attribute[] = implode(':', $single_attribute);
        }
        $output_attribute = implode('; ', $output_attribute);

        $chip_json = get_chip_json([
            'series_number' => intval($row[1]),
            'name' => $row[3],
            'description' => $row[4],
            'attributes' => $attributes,
            'uuid' => $row[7]
        ], $valid_rows);

        // display only one sample data in 'chip-0007 format'
        if ($display_sample) {
            echo "\n***********************************************************************\n";
            echo "***********************************************************************\n\n";
            echo "Below is a sample of what a row looks like in chip-0007 format";
            echo "\n\n***********************************************************************\n\n";
            echo json_encode($chip_json, JSON_PRETTY_PRINT);
            echo "\n\n***********************************************************************\n\n";
            echo "Above is a sample of what a row looks like in chip-0007 format";
            echo "\n\n***********************************************************************\n";
            echo "***********************************************************************\n\n";
            $display_sample = false;
        }

        $chip_json = json_encode($chip_json);

        $chip_hash = hash("sha256", $chip_json);
        $output_rows[$i][6] = $output_attribute;
        $output_rows[$i][count($expected_columns)] = $chip_hash;
    }
}

// export output to csv
$output_file = fopen($output_file_name, "w");
foreach ($output_rows as $row) {
    fputcsv($output_file, $row);
}
fclose($output_file);

echo "\n\n";
echo "FILE HAS BEEN EXPORTED SUCCESSFULLY\n\n";
echo "Your file is located at : " . __DIR__ . '\\' . $output_file_name;
echo "\n\n";

function row_is_valid($row, $expected_columns)
{
    // check the number of columns
    if (count($row) < count($expected_columns)) {
        return false;
    }

    // check if the first column is a number (i.e series number)
    if (!is_numeric($row[1])) {
        return false;
    }

    // check if there is an empty column
    // foreach ($row as $column) {
    //     if (empty(trim($column))) {
    //         return false;
    //     }
    // }

    return true;
}

function num_of_valid_rows($rows, $expected_columns)
{
    $count = 0;

    foreach ($rows as $row) {
        if (row_is_valid($row, $expected_columns)) {
            $count++;
        }
    }

    return $count;
}

function get_rows($file_name)
{
    $rows = [];
    $file = fopen($file_name, "r");

    while (($row = fgetcsv($file))) {
        $rows[] = $row;
    }

    return $rows;
}

function get_chip_json(array $value, int $total)
{
    return [
        "format" => "CHIP-0007",
        "name" => $value['name'],
        "description" => $value['description'],
        "sensitive_content" => false,
        "series_number" => $value['series_number'],
        "series_total" => $total,
        "attributes" => $value['attributes'],
        "collection" => [
            "name" => "HNGi9 Collection",
            "id" => $value['uuid'],
            "attributes" => [
                [
                    "type" => "description",
                    "value" => "NFTs for free lunch on HNGi9"
                ]
            ]
        ]
    ];
}

function file_has_columns($file_name, $expected_columns)
{
    $available_columns = [];
    $missing_columns =  [];

    foreach (fgetcsv(fopen($file_name, "r")) as $column) {
        $available_columns[] = strtolower($column);
    }

    foreach ($expected_columns as $column) {
        if (!in_array($column, $available_columns)) {
            $missing_columns[] = $column;
        }
    }

    return count($missing_columns) ? $missing_columns : true;
}

function file_is_csv(string $name)
{
    $csv_mimes = [
        'text/csv',
        'text/plain',
        'application/csv',
        'text/comma-separated-values',
        'application/excel',
        'application/vnd.ms-excel',
        'application/vnd.msexcel',
        'text/anytext',
        'application/octet-stream',
        'application/txt',
    ];

    return (in_array(mime_content_type($name), $csv_mimes));
}

function file_is_valid(string $file)
{
    return file_exists($file);
}

function get_row_attributes(string $attribute)
{
    $array = [];

    $attribute = str_replace(';', '";"', $attribute);
    $attribute = str_replace([',', '.'], '";"', $attribute);
    $attribute = str_replace(' ', '', $attribute);
    $attribute_array = explode(';', $attribute);
    foreach ($attribute_array as $single_attribute) {
        $single_attribute = str_replace('"', '', $single_attribute);
        $single_attribute_array = explode(':', $single_attribute);

        if (array_key_exists(0, $single_attribute_array) && array_key_exists(1, $single_attribute_array)) {
            $array[$single_attribute_array[0]] = $single_attribute_array[1];
        }
    }

    return $array;
}

<?php
// create_tables.php
require_once __DIR__.'/includes/db.php';

$sql_queries = [
    "CREATE TABLE IF NOT EXISTS drug_histories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT NOT NULL,
        drug_name VARCHAR(255),
        indication VARCHAR(255),
        start_date DATE,
        stop_date DATE,
        max_dose VARCHAR(100),
        route VARCHAR(50),
        response TEXT,
        adverse_effects TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS current_histories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT NOT NULL,
        chief_complaint TEXT,
        onset_date DATE,
        duration_text VARCHAR(100),
        course VARCHAR(100),
        aggravating_factors TEXT,
        relieving_factors TEXT,
        associated_symptoms TEXT,
        red_flags TEXT,
        general_condition TEXT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
    )"
];

echo "<h3>Creating Missing Tables</h3>";
foreach ($sql_queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "<p style='color:green'>✓ Table created successfully</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
    }
}
echo "<h4>Done!</h4>";
?>
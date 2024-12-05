<?php
function criarTabelaTemporaria($conn, $nomeTabela, $colunas){
  $sql = "CREATE TABLE IF NOT EXISTS $nomeTabela ($colunas)";

  if ($conn->query($sql) === TRUE) {
    echo "Tabela  temporária criada.\n";
  } else {
      echo "Erro ao criar tabela  temporária: " . $conn->error . "\n";
  }
}


function formatarData($data, $hora = "00:00:00") {
  // Tenta criar a data no formato d/m/Y H:i:s
  $dataHoraFormatada = DateTime::createFromFormat('d/m/Y H:i:s', "$data $hora");
  if ($dataHoraFormatada && $dataHoraFormatada->format('d/m/Y H:i:s') === "$data $hora") {
    return $dataHoraFormatada->format('Y-m-d H:i:s'); // Formato MySQL com data e hora
  }

  // Se falhar, tenta criar a data no formato Y-m-d H:i:s
  $dataHoraFormatada = DateTime::createFromFormat('Y-m-d H:i:s', "$data $hora");
  if ($dataHoraFormatada && $dataHoraFormatada->format('Y-m-d H:i:s') === "$data $hora") {
    return $dataHoraFormatada->format('Y-m-d H:i:s'); // Formato MySQL com data e hora
  }

  return "NULL"; 
}

function formatarSexo($sexo) {
  return $sexo === 'M' ? 'Masculino' : 'Feminino';
}

function importarCSV($conn, $nomeTabela, $arquivo) {
  $handle = fopen($arquivo, 'r');
  if (!$handle) {
      die("Erro ao abrir o arquivo: $arquivo");
  }
  fgetcsv($handle, 1000, ";");

  while (($dados = fgetcsv($handle, 1000, ";")) !== false) {
      // Se a data de nascimento (índice 2) for inválida, substitui por NULL
      $dados[2] = formatarData($dados[2]);
      if ($dados[7] === 'M' || $dados[7] === 'F')  
        $dados[7] = formatarSexo($dados[7]); 
      
      // Preenche campos vazios com NULL
      $campos = implode(", ", array_map(fn($coluna) => $coluna === '' ? "NULL" : "'$coluna'", $dados)); 

      $sql = "INSERT INTO $nomeTabela VALUES ($campos)";

      if (!$conn->query($sql)) {
          echo "Erro ao inserir na tabela $nomeTabela: " . $conn->error . "\n";
      }
  }
  fclose($handle);
}

function transformarDados($connTemp) {
  // Adiciona as colunas data_hora_inicio e data_hora_fim à tabela agendamentos
  $alter = "ALTER TABLE agendamentos ADD COLUMN data_hora_inicio DATETIME, ADD COLUMN data_hora_fim DATETIME, ADD COLUMN cod_procedimento INT";
  if ($connTemp->query($alter) === TRUE) {
    echo "Colunas data_hora_inicio e data_hora_fim adicionadas à tabela agendamentos.\n";
  } else {
    die("Erro ao adicionar colunas: " . $connTemp->error);
  }

  // Seleciona os dados da tabela agendamentos
  $sqlSelect = "SELECT cod_agendamento, dia, hora_inicio, hora_fim FROM agendamentos";
  $result = $connTemp->query($sqlSelect);

  if ($result === false) {
    die("Erro ao selecionar dados da tabela agendamentos: " . $connTemp->error);
  }

  // Atualiza os dados na tabela agendamentos
  while ($row = $result->fetch_assoc()) {
    $codAgendamento = $row['cod_agendamento'];
    $dataHoraInicio = formatarData($row['dia'], $row['hora_inicio']);
    $dataHoraFim = formatarData($row['dia'], $row['hora_fim']);

    $sqlUpdate = "UPDATE agendamentos SET data_hora_inicio = ?, data_hora_fim = ? WHERE cod_agendamento = ?";
    $stmtUpdate = $connTemp->prepare($sqlUpdate);
    $stmtUpdate->bind_param('ssi', $dataHoraInicio, $dataHoraFim, $codAgendamento);

    if ($stmtUpdate->execute()) {
      echo "Dados atualizados para o agendamento $codAgendamento.\n";
    } else {
      echo "Erro ao atualizar dados para o agendamento $codAgendamento: " . $stmtUpdate->error . "\n";
    }

    $stmtUpdate->close();
  }
}

function migrarDados($connTemp, $connMedical, $nomeTabelaTemp, $nomeTabelaMigra, $campoChecagemTemp, $campoChecagemMigra, $colunasTemp, $colunasMigra) {
  // Seleciona todos os dados da tabela temporária
  $sqlSelect = "SELECT $colunasTemp FROM $nomeTabelaTemp";
  $result = $connTemp->query($sqlSelect);

  if ($result === false) {
      die("Erro ao selecionar dados da tabela temporária: " . $connTemp->error);
  }

  if ($result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
          // Verifica se o registro já existe na tabela de destino
          $campoChecagemValor = $row[$campoChecagemTemp];

          // Preparando a consulta de verificação
          $sqlCheck = "SELECT 1 FROM $nomeTabelaMigra WHERE $campoChecagemMigra = ?";
          $stmtCheck = $connMedical->prepare($sqlCheck);
          $stmtCheck->bind_param('s', $campoChecagemValor);
          $stmtCheck->execute();
          $stmtCheck->store_result();

          if ($stmtCheck->num_rows === 0) {
              $valores = array_map(function($value) use ($connMedical) {
                  return $value === null ? "NULL" : "'" . $connMedical->real_escape_string($value) . "'";
              }, array_values($row));

              $sqlInsert = "INSERT INTO $nomeTabelaMigra ($colunasMigra) VALUES (" . implode(", ", $valores) . ")";

              if ($connMedical->query($sqlInsert) === TRUE) {
                  echo "Registro inserido com sucesso na tabela $nomeTabelaMigra.\n";
              } else {
                  echo "Erro ao inserir registro na tabela $nomeTabelaMigra: " . $connMedical->error . "\n";
              }
          } 

          $stmtCheck->close();
      }
  }
}

function atualizarIDsTemp($connTemp, $connMedical, $nomeTabelaTemp, $nomeTabelaMigra, $campoChecagemTemp, $campoChecagemMigra, $campoIDTemp, $campoIDMigra='id'): void {
  $sqlSelect = "SELECT $campoChecagemMigra, $campoIDMigra FROM $nomeTabelaMigra";
  $result = $connMedical->query($sqlSelect);

  if ($result === false) {
    die("Erro ao selecionar dados da tabela de migração: " . $connMedical->error);
  }

  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $campoChecagemValor = $row[$campoChecagemMigra];
      $idMigra = $row[$campoIDMigra];

      $sqlUpdateTemp = "UPDATE $nomeTabelaTemp SET $campoIDTemp = ? WHERE $campoChecagemTemp = ?";
      $stmtUpdateTemp = $connTemp->prepare($sqlUpdateTemp);
      $stmtUpdateTemp->bind_param('is', $idMigra, $campoChecagemValor);
      if ($stmtUpdateTemp->execute()) {
        echo "ID atualizado na tabela temporária para $campoChecagemValor.\n";
      } else {
        echo "Erro ao atualizar ID na tabela temporária: " . $stmtUpdateTemp->error . "\n";
      }
      $stmtUpdateTemp->close();
    }
  } else {
    echo "Nenhum registro encontrado na tabela $nomeTabelaMigra.\n";
  }
}

function dateNow(){
  date_default_timezone_set('America/Sao_Paulo');
  return date('d-m-Y \à\s H:i:s');
}
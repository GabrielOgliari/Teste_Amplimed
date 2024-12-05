<?php
/*
  Descrição do Desafio:
    Você precisa realizar uma migração dos dados fictícios que estão na pasta <dados_sistema_legado> para a base da clínica fictícia MedicalChallenge.
    Para isso, você precisa:
      1. Instalar o MariaDB na sua máquina. Dica: Você pode utilizar Docker para isso;
      2. Restaurar o banco da clínica fictícia Medical Challenge: arquivo <medical_challenge_schema>;
      3. Migrar os dados do sistema legado fictício que estão na pasta <dados_sistema_legado>:
        a) Dica: você pode criar uma função para importar os arquivos do formato CSV para uma tabela em um banco temporário no seu MariaDB.
      4. Gerar um dump dos dados já migrados para o banco da clínica fictícia Medical Challenge.
*/

// Importação de Bibliotecas:
include "./lib.php";

// Conexão com o banco da clínica fictícia:
$connMedical = mysqli_connect("localhost", "root", "masterkey", "MedicalChallenge")
  or die("Não foi possível conectar os servidor MySQL: MedicalChallenge\n");

// Conexão com o banco temporário:
$connTemp = mysqli_connect("localhost", "root", "masterkey", "0temp")
  or die("Não foi possível conectar os servidor MySQL: 0temp\n");

// Informações de Inicio da Migração:
echo "Início da Migração: " . dateNow() . ".\n\n";


// Criando a tabelas temporárias:
$temp_paciente ="
  cod_paciente INT,
  nome_paciente VARCHAR(255),
  nasc_paciente DATE,
  pai_paciente VARCHAR(255),
  mae_paciente VARCHAR(255),
  cpf_paciente VARCHAR(20),
  rg_paciente VARCHAR(20),
  sexo_pac ENUM('Masculino', 'Feminino') NOT NULL,
  id_conv INT,
  convenio VARCHAR(255),
  obs_clinicas TEXT
";
criarTabelaTemporaria($connTemp, "pacientes", $temp_paciente);

$temp_agendamentos="
  cod_agendamento INT,
  descricao TEXT,
  dia DATE,
  hora_inicio TIME,
  hora_fim TIME,
  cod_paciente INT,
  paciente VARCHAR(255),
  cod_medico INT,
  medico VARCHAR(255),
  cod_convenio INT,
  convenio VARCHAR(255),
  procedimento VARCHAR(255)
";
criarTabelaTemporaria($connTemp, "agendamentos", $temp_agendamentos);

// Importando os dados para a tabela temporária:
importarCSV($connTemp, "pacientes", "./dados_sistema_legado/20210512_pacientes.csv");
importarCSV($connTemp, "agendamentos", "./dados_sistema_legado/20210512_agendamentos.csv");

transformarDados($connTemp);

// Migrando os dados para a tabela convenios
migrarDados( $connTemp,  $connMedical, "pacientes", "convenios", "convenio", "nome", "convenio", "nome");
atualizarIDsTemp($connTemp, $connMedical,"pacientes", "convenios", "convenio","nome", "id_conv");
atualizarIDsTemp($connTemp, $connMedical,"agendamentos", "convenios", "convenio","nome", "cod_convenio");

// Migrando os dados para a tabela pacientes
migrarDados( $connTemp,  $connMedical, "pacientes", "pacientes", "cpf_paciente", "cpf", "nome_paciente, nasc_paciente, cpf_paciente, rg_paciente, sexo_pac, id_conv", "nome, nascimento, cpf, rg, sexo, id_convenio");
atualizarIDsTemp($connTemp, $connMedical,"pacientes", "pacientes", "cpf_paciente", "cpf", "cod_paciente");
atualizarIDsTemp($connTemp, $connTemp,"agendamentos", "pacientes", "paciente", "nome_paciente", "cod_paciente", "cod_paciente");

// Migrando os dados para a tabela processedimentos
migrarDados( $connTemp,  $connMedical, "agendamentos",  "procedimentos", "procedimento", "nome", "procedimento", "nome");
atualizarIDsTemp($connTemp, $connMedical,"agendamentos", "procedimentos", "procedimento", "nome", "cod_procedimento");

// Migrando os dados para a tabela profissionais
migrarDados( $connTemp,  $connMedical, "agendamentos",  "profissionais", "medico", "nome", "medico", "nome");
atualizarIDsTemp($connTemp, $connMedical,"agendamentos", "profissionais", "medico", "nome", "cod_medico");

// Migrando os dados para a tabela agendamentos
migrarDados( $connTemp,  $connMedical, "agendamentos",  "agendamentos", "data_hora_inicio","dh_inicio", "cod_paciente, cod_medico, data_hora_inicio, data_hora_fim, cod_convenio, cod_procedimento, descricao", "id_paciente, id_profissional, dh_inicio, dh_fim, id_convenio, id_procedimento, observacoes");

// Encerrando as conexões:
$connMedical->close();
$connTemp->close();

// Informações de Fim da Migração:
echo "Fim da Migração: " . dateNow() . ".\n";


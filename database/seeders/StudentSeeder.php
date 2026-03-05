<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Student;
use App\Models\Group;
use App\Models\StudentGroupHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $studentData = [
            ['3110', 'U99414588', 'GONZÁLEZ SAAVEDRA YOSGART LISHAEL','','','','',''],
            ['3110', 'U99415631', 'GONZÁLEZ SEVILLA JESSEY ABRIL','','','','',''],
            ['3110', 'U99415638', 'MANZO GOOVAERTS NATALIA VALENTINA','','','','',''],
            ['3110', 'U99414457', 'PIRRON SANCHEZ GURI','','','','',''],

            ['4110', 'U99406991', 'BALBUENA MARQUEZ IAN VLADIMIR','','','','',''],
            ['4110', 'U99408100', 'CORNEJO MORENO DANIELA','','','','',''],
            ['4110', 'U99411329', 'FLORES AVALOS MARIO ALEJANDRO','','','','',''],
            ['4110', 'U99405769', 'FLORES GARCIA TERUEL MAUREEN','','','','',''],
            ['4110', 'U99414112', 'FRAILE GONZÁLEZ ANA SOFIA','','','','',''],
            ['4110', 'U99410815', 'HERNÁNDEZ RIOS ANDREA','','','','',''],
            ['4110', 'U99411790', 'HERNÁNDEZ VALENZO ALISON','','','','',''],
            ['4110', 'U99411514', 'HURTADO MURATALLA VALERIA GERALDINE','','','','',''],
            ['4110', 'U99400585', 'IBARRA HERNANDEZ CARLOS ENRIQUE','','','','',''],
            ['4110', 'U99410083', 'JUAREZ VILLATORO JOSEMARIA','','','','',''],
            ['4110', 'U99407307', 'MATEOS ALVARADO ÁNGEL LEONARDO','','','','',''],
            ['4110', 'U99414336', 'MENDOZA GUZMÁN IRLANDA NATALIA','','','','',''],
            ['4110', 'U99398410', 'MERIDA HERNÁNDEZ INGRID DANNAE','','','','',''],
            ['4110', 'U99400872', 'MEZA ALONSO SANTIAGO','','','','',''],
            ['4110', 'U99407422', 'MORIN COLIN ULISES','','','','',''],
            ['4110', 'U99407193', 'OLIVARES VILLARREAL FERNANDA GISSELLE','','','','',''],
            ['4110', 'U99407242', 'PÉREZ MENDOZA MARÍA JOSÉ','','','','',''],
            ['4110', 'U99407234', 'REYES RODRIGUEZ MARIA FERNANDA','','','','',''],
            ['4110', 'U99420261', 'RODRIGUEZ ALCERRECA DIEGO','','','','',''],
            ['4110', 'U99409765', 'RODRÍGUEZ ORNELAS VERONICA ENID','','','','',''],
            ['4110', 'U99407399', 'SALDIVAR MORENO EMILY','','','','',''],
            ['4110', 'U99416745', 'SALMERON HERNÁNDEZ XIMENA SOFIA','','','','',''],
            ['4110', 'U99426665', 'VERGARA BARRIENTOS MARCO ANTONIO','','','','',''],
            ['4110', 'U99399409', 'VILLEGAS DORANTES SAMANTHA','','','','',''],

            ['5110', 'U99398925', 'BERNAL GALVAN VALENTINA','','','','',''],
            ['5110', 'U99397796', 'ESPINOSA GARCÍA IAN','','','','',''],
            ['5110', 'U99401998', 'HERNANDEZ LEON LIZETH','','','','',''],
            ['5110', 'U99398879', 'HERNÁNDEZ ROJAS VALENTINA','','','','',''],
            ['5110', 'U99400582', 'IBARRA HERNANDEZ VICTOR JESUS','','','','',''],
            ['5110', 'U99393825', 'MIRELES RUIZ ALAN','','','','',''],
            ['5110', 'U99412417', 'MORENO IBARRA MARÍA JOSÉ','','','','',''],
            ['5110', 'U99402243', 'SALAZAR MARTÍNEZ KARYME','','','','',''],
            ['5110', 'U99401285', 'SAN PEDRO MEJÍA HECTOR DANIEL','','','','',''],
            ['5110', 'U99401073', 'VELAZQUEZ CID ISAAC','','','','',''],

            ['5120', 'U99403912', 'ALAMILLO ROJAS KALEB','','','','',''],
            ['5120', 'U99403989', 'BALTAZAR URZUA FRIDA NATHALIA','','','','',''],
            ['5120', 'U99401264', 'BUSTAMANTE LÓPEZ NOEMÍ ALEJANDRA','','','','',''],
            ['5120', 'U99403899', 'CASTILLO GARCÍA ANGEL EDUARDO','','','','',''],
            ['5120', 'U99398765', 'CONTRERAS LUGO ISABELLA','','','','',''],
            ['5120', 'U99399376', 'FRANCO JUAREZ SOFIA','','','','',''],
            ['5120', 'U99402274', 'GARDUÑO HERNANDEZ ALEJANDRO','','','','',''],
            ['5120', 'U99402127', 'HERNÁNDEZ HERRERA MARIANA','','','','',''],
            ['5120', 'U99411113', 'HERNANDEZ TAPIA RODRIGO','','','','',''],
            ['5120', 'U99402119', 'JUAREZ BELLO ALINE YESEREL','','','','',''],
            ['5120', 'U99403750', 'NARVÁEZ JAIMES MARCO MANUEL','','','','',''],
            ['5120', 'U99401895', 'NAVA ROMANO EISEN ALIZEE','','','','',''],
            ['5120', 'U99403888', 'NAVARRETE CHAVARRIA ASHLEY','','','','',''],
            ['5120', 'U99401256', 'PIÑA REYES ALFREDO','','','','',''],
            ['5120', 'U99409174', 'RAMOS ORTEGA JOVANNY ALEXIS','','','','',''],
            ['5120', 'U99401125', 'REYES DAVILA SOFIA ADELI','','','','',''],
            ['5120', 'U99400101', 'RODRÍGUEZ GÓMEZ VALENTINA','','','','',''],
            ['5120', 'U99406907', 'RODRIGUEZ OLMOS OMAR','','','','',''],
            ['5120', 'U99400568', 'RODRÍGUEZ SUAREZ ZULEYKA MAILYN','','','','',''],
            ['5120', 'U99400625', 'SANTIBAÑES CRUZ PATRICIO','','','','',''],
            ['5120', 'U99398975', 'TENORIO SALAZAR CRISTINA VALERIA','','','','',''],
            ['5120', 'U99400464', 'TORRES CRUZ DIEGO FERNANDO','','','','',''],
            ['5120', 'U99403934', 'URQUIJO MUÑOZ ANDREA','','','','',''],
            ['5120', 'U99401287', 'ZARIÑANA BUENDIA ANDREA MELISSA','','','','',''],

            ['6120', 'U99387597', 'BALDERAS SANDOVAL MARIO ALEJANDRO','','','','',''],
            ['6120', 'U99410843', 'BARCENAS GUERRA KARLA JOVANNA','','','','',''],
            ['6120', 'U99391858', 'DAVILA MORALES EMILIO','','','','',''],
            ['6120', 'U99421743', 'DE LA CRUZ TELLO DE MENESES PABLO','','','','',''],
            ['6120', 'U99391548', 'GONZÁLEZ RODRÍGUEZ FRIZIA VALENTINA','','','','',''],
            ['6120', 'U99410870', 'HERNÁNDEZ TABOADA SEBASTIAN','','','','',''],
            ['6120', 'U99407207', 'HERNÁNDEZ VALENZO RADAMES','','','','',''],
            ['6120', 'U99405602', 'HURTADO PILGRAN DIEGO IMANOL','','','','',''],
            ['6120', 'U99',       'LOPEZ GONZALEZ ASAF','','','','',''],
            ['6120', 'U99388773', 'MARTÍNEZ ROMERO CHRISTIE GERALDINE','','','','',''],
            ['6120', 'U99381444', 'REYES RODRÍGUEZ GLADYS PAULINA','','','','',''],
            ['6120', 'U99394859', 'SAN ELIAS BUSTOS VALERIA','','','','',''],

        ];
        foreach ($studentData as $row) {

            $groupName = $row[0];
            $enrollmentNumber = $row[1];
            $fullName = $row[2];
        
            $group = Group::where('name', $groupName)->first();
        
            if (! $group) {
                continue; // seguridad
            }
        
            $user = User::create([
                'email' => $enrollmentNumber . '@mail.com',
                'name' => $fullName,
                'password' => Hash::make('123123123'),
            ]);
        
            $user->assignRole('student');
        
            $student = Student::create([
                'user_id' => $user->id,
                'group_id' => $group->id,
                'enrollment_number' => $enrollmentNumber,
                'is_active' => true,
            ]);
        
            // 🔑 HISTORIAL INICIAL (CLAVE)
            StudentGroupHistory::create([
                'student_id' => $student->id,
                'group_id'   => $group->id,
                'start_date' => Carbon::create(2026, 1, 12), // inicio del curso
                'end_date'   => null,
                'reason'     => 'Ingreso inicial',
            ]);
        }
    }
}

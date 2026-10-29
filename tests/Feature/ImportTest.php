<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Fixture;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use Fixture,RefreshDatabase;

    public function test_student_csv_preview_commit_and_duplicate_rejection(): void
    {
        $f = $this->fixture();
        $csv = "roll_number,name,email,section_id\nTEST-NEW,New Student,new@example.test,".$f['section']->id."\n";
        $this->actingAs($f['admin'])->postJson('/workflow/import', ['kind' => 'students', 'file' => UploadedFile::fake()->createWithContent('students.csv', $csv)])->assertOk()->assertJsonPath('committed', false);
        $this->assertDatabaseCount('users', 6);
        $this->postJson('/workflow/import', ['kind' => 'students', 'commit' => 1, 'file' => UploadedFile::fake()->createWithContent('students.csv', $csv)])->assertOk()->assertJsonPath('committed', true);
        $this->assertDatabaseCount('users', 7);
        $this->postJson('/workflow/import', ['kind' => 'students', 'commit' => 1, 'file' => UploadedFile::fake()->createWithContent('students.csv', $csv)])->assertUnprocessable();
        $this->assertDatabaseCount('users', 7);
    }

    public function test_marks_csv_scope_absence_and_duplicates(): void
    {
        $f = $this->fixture();
        $csv = "roll_number,component_id,score,absent\nTEST-0,".$f['component']->id.",86,0\nTEST-1,".$f['component']->id.",,1\n";
        $this->actingAs($f['replacement'])->postJson('/workflow/import', ['kind' => 'marks', 'course_id' => $f['ec']->id, 'file' => UploadedFile::fake()->createWithContent('marks.csv', $csv)])->assertForbidden();
        $this->actingAs($f['teacher'])->postJson('/workflow/import', ['kind' => 'marks', 'course_id' => $f['ec']->id, 'commit' => 1, 'file' => UploadedFile::fake()->createWithContent('marks.csv', $csv)])->assertOk();
        $this->assertDatabaseCount('marks', 2);
        $this->assertDatabaseHas('marks', ['student_id' => $f['other']->id, 'absent' => true, 'score' => null]);
        $this->postJson('/workflow/import', ['kind' => 'marks', 'course_id' => $f['ec']->id, 'commit' => 1, 'file' => UploadedFile::fake()->createWithContent('marks.csv', $csv.'TEST-0,'.$f['component']->id.",90,0\n")])->assertUnprocessable();
    }

    public function test_excel_xlsx_import_and_formula_rejection(): void
    {
        $f = $this->fixture();
        $path = tempnam(sys_get_temp_dir(), 'examcell-xlsx');
        $z = new \ZipArchive;
        $z->open($path, \ZipArchive::OVERWRITE);
        $z->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
        $z->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Students" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $z->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/></Relationships>');
        $sheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">';
        foreach (['roll_number', 'name', 'email', 'section_id'] as $i => $header) {
            $sheet .= '<c r="'.chr(65 + $i).'1" t="inlineStr"><is><t>'.$header.'</t></is></c>';
        }$sheet .= '</row><row r="2">';
        foreach (['XLSX-1', 'Excel Student', 'excel@example.test', (string) $f['section']->id] as $i => $value) {
            $sheet .= '<c r="'.chr(65 + $i).'2" t="inlineStr"><is><t>'.$value.'</t></is></c>';
        }$sheet .= '</row></sheetData></worksheet>';
        $z->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $z->close();
        try {
            $this->actingAs($f['admin']);
            $upload = UploadedFile::fake()->createWithContent('students.xlsx', file_get_contents($path));
            $this->postJson('/workflow/import', ['kind' => 'students', 'commit' => 1, 'file' => $upload])->assertOk()->assertJsonPath('committed', true);
            $this->assertDatabaseHas('student_profiles', ['roll_number' => 'XLSX-1']);
            $z = new \ZipArchive;
            $z->open($path);
            $z->addFromString('xl/worksheets/sheet1.xml', str_replace('<c r="D2" t="inlineStr">', '<c r="D2" t="inlineStr"><f>1+1</f>', $sheet));
            $z->close();
            $upload = UploadedFile::fake()->createWithContent('formula.xlsx', file_get_contents($path));
            $this->postJson('/workflow/import', ['kind' => 'students', 'file' => $upload])->assertUnprocessable();
        } finally {
            unlink($path);
        }
    }
}

<?php

class local_codeverify_observer {
    public static function test(assignsubmission_file\event\assessable_uploaded $event) {
        global $CFG;
        global $DB;

        $user_id = $event->get_data()['userid'];

        foreach($event->get_data()['other']['pathnamehashes'] as $fh){
            $fs = get_file_storage();
            $file = $fs->get_file_by_hash($fh);
            $filename = $file->get_filename();

            $fch = $file->get_contenthash();
            $url = $CFG->dataroot . '/filedir/' . substr($fch, 0, 2) . '/' .
                substr($fch, 2, 2) . '/' . $fch;

            if(substr($filename, -2) != ".c") return;

            $cmd = "gcc -x c " . $url . " -o " . $CFG->dataroot . "/tmp.exe 2>&1";
            $s = str_replace($url.':', '', shell_exec($cmd));

            if($s == '') {
                $smessage = $filename.' é um codigo C válido.';
                $subject = $filename.' enviado com sucesso!';
            } else {
                $smessage = 'Ocorreu um erro na compilação do arquivo ' .$filename.
                    '. <br> O codigo foi enviado mas ainda pode ser editado. <br><br>'.
                    nl2br($s);
                $subject = $filename.' contém erros';
            }

            //shell_exec("echo '$s' > /var/www/html/moodle/test.txt");

            $user = $DB->get_record('user', array('id' => $user_id));

            $message = new \core\message\message();
            $message->component = 'moodle';
            $message->name = 'instantmessage';
            $message->userfrom = $DB->get_record('user', array('id' => 1));
            $message->userto = $user;
            $message->subject = $subject;
            $message->fullmessageformat = FORMAT_MARKDOWN;
            $message->fullmessagehtml = '<p>'.$smessage.'</p>';
            $message->notification = '1';
            $message->courseid = $event->get_data['courseid']; // This is required in recent versions, use it from 3.2 on https://tracker.moodle.org/browse/MDL-47162

            message_send($message);
        }
    }
}

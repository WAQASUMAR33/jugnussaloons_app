<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_logo',
        'brand_name',
        'brand_slogan',
        'brand_address',
        'brand_phone1',
        'brand_phone2',
        'whatsapp_birthday_message',
        'whatsapp_anniversary_message',
        'pos_receipt_note',
        'appointment_receipt_note',
    ];

    /**
     * Default POS print receipt note.
     */
    public static function defaultPosReceiptNote(): string
    {
        return "Goods once sold can be exchanged within 7 days with original receipt. No cash refunds. Thank you for shopping with us!";
    }

    /**
     * Default Appointment booking print receipt note.
     */
    public static function defaultAppointmentReceiptNote(): string
    {
        return "Appointments are subject to slot availability. Please arrive 10 minutes prior to your scheduled time. Thank you for choosing us!";
    }

    /**
     * Default birthday wishing message template.
     */
    public static function defaultBirthdayMessage(): string
    {
        return "Happy Birthday {name}! 🎂🎉 Wishing you a wonderful day filled with happiness and joy from all of us at {salon_name}. Visit us today to enjoy your special celebration discount and VIP pampering!";
    }

    /**
     * Default marriage ceremony / anniversary wishing message template.
     */
    public static function defaultAnniversaryMessage(): string
    {
        return "Happy Marriage Anniversary {name}! 💍✨ Wishing you both a lifetime of love, joy, and togetherness from the team at {salon_name}. Celebrate your special marriage ceremony milestone with our luxury pampering packages!";
    }

    /**
     * Generate customized birthday wishing message.
     */
    public function getBirthdayMessageFor(string $name, ?string $milestone = null, ?string $date = null): string
    {
        $template = !empty($this->whatsapp_birthday_message) ? $this->whatsapp_birthday_message : self::defaultBirthdayMessage();
        $replacements = [
            '{name}' => $name,
            '{salon_name}' => $this->brand_name ?: 'Jugnu Saloon',
            '{milestone}' => $milestone ?: 'Birthday',
            '{date}' => $date ?: now()->format('M d'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Generate customized marriage ceremony / anniversary wishing message.
     */
    public function getAnniversaryMessageFor(string $name, ?string $milestone = null, ?string $date = null): string
    {
        $template = !empty($this->whatsapp_anniversary_message) ? $this->whatsapp_anniversary_message : self::defaultAnniversaryMessage();
        $replacements = [
            '{name}' => $name,
            '{salon_name}' => $this->brand_name ?: 'Jugnu Saloon',
            '{milestone}' => $milestone ?: 'Marriage Anniversary',
            '{date}' => $date ?: now()->format('M d'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Retrieve global active brand settings singleton or create default.
     * Uses static cache to avoid repeated DB queries within the same request.
     */
    public static function getSettings(): self
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $cached = self::firstOrCreate(
            ['id' => 1],
            [
                'brand_name' => 'Jugnu Saloon',
                'brand_slogan' => 'Executive Hair Styling & Grooming Spa',
                'brand_address' => 'Main Boulevard, Saloon Commercial District, City Center',
                'brand_phone1' => '+92 300 1234567',
                'brand_phone2' => '+92 321 7654321',
                'whatsapp_birthday_message' => self::defaultBirthdayMessage(),
                'whatsapp_anniversary_message' => self::defaultAnniversaryMessage(),
                'pos_receipt_note' => self::defaultPosReceiptNote(),
                'appointment_receipt_note' => self::defaultAppointmentReceiptNote(),
            ]
        );

        return $cached;
    }
}

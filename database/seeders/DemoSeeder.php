<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\InsuranceClaim;
use App\Models\InsuranceCompany;
use App\Models\Payment;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $pharmacies = collect([
            ['name'=>'Pharmacie KM5','city'=>'Kaloum · Conakry'],
            ['name'=>'Pharmacie Ratoma','city'=>'Ratoma · Conakry'],
            ['name'=>'Pharmacie Kindia','city'=>'Kindia · Basse-Guinée'],
        ])->map(fn($p)=>Pharmacy::create($p+['is_active'=>true]));

        $user = User::firstOrCreate(['email'=>'admin@pharmagest.test'],[
            'name'=>'Admin PharmaGest',
            'password'=>Hash::make('password'),
        ]);
        foreach ($pharmacies as $p) {
            $user->pharmacies()->syncWithoutDetaching([$p->id=>['role'=>'administrateur']]);
        }

        $vendeur = User::firstOrCreate(['email'=>'caisse@pharmagest.test'],[
            'name'=>'Aïcha Diallo',
            'password'=>Hash::make('password'),
        ]);
        $vendeur->pharmacies()->syncWithoutDetaching([$pharmacies[0]->id=>['role'=>'vendeur']]);

        $categories = collect(['Antalgiques','Antibiotiques','Antipaludéens','Vitamines','Dispositifs'])
            ->map(fn($n)=>Category::create(['name'=>$n]));

        $productsData = [
            ['code'=>'DOLI1000','name'=>'Doliprane 1000mg ×16','generic_name'=>'Paracétamol','laboratory'=>'Sanofi','category'=>'Antalgiques','sale_price'=>28500,'purchase_price'=>19000,'threshold'=>40],
            ['code'=>'AMOX500','name'=>'Amoxicilline 500mg ×21','generic_name'=>'Amoxicilline','laboratory'=>'Biogaran','category'=>'Antibiotiques','sale_price'=>45000,'purchase_price'=>32000,'threshold'=>30],
            ['code'=>'ARTECOMBO','name'=>'Artem Combo 80/480 ×6','generic_name'=>'Artemether + Lumef.','laboratory'=>'Ajanta','category'=>'Antipaludéens','sale_price'=>62000,'purchase_price'=>44000,'threshold'=>25],
            ['code'=>'VITC','name'=>'Vitamine C 1g ×10 eff.','generic_name'=>'Vitamine C','laboratory'=>'UPSA','category'=>'Vitamines','sale_price'=>35000,'purchase_price'=>22000,'threshold'=>20],
            ['code'=>'IBU400','name'=>'Ibuprofène 400mg ×30','generic_name'=>'Ibuprofène','laboratory'=>'Biogaran','category'=>'Antalgiques','sale_price'=>22000,'purchase_price'=>15000,'threshold'=>50],
            ['code'=>'AMLO10','name'=>'Amlodipine 10mg ×30','generic_name'=>'Amlodipine','laboratory'=>'Teva','category'=>'Antalgiques','sale_price'=>38000,'purchase_price'=>26000,'threshold'=>30],
            ['code'=>'INSNPH','name'=>'Insuline NPH 100UI flacon','generic_name'=>'Insuline','laboratory'=>'Novo Nordisk','category'=>'Dispositifs','sale_price'=>185000,'purchase_price'=>150000,'threshold'=>15],
            ['code'=>'ARTEINJ','name'=>'Artemether 80mg inj.','generic_name'=>'Artemether','laboratory'=>'Ajanta','category'=>'Antipaludéens','sale_price'=>95000,'purchase_price'=>70000,'threshold'=>25],
            ['code'=>'CAPTO25','name'=>'Captopril 25mg ×60','generic_name'=>'Captopril','laboratory'=>'Teva','category'=>'Antalgiques','sale_price'=>42000,'purchase_price'=>30000,'threshold'=>40],
            ['code'=>'PARA1G','name'=>'Paracétamol 1g ×16','generic_name'=>'Paracétamol','laboratory'=>'Sanofi','category'=>'Antalgiques','sale_price'=>18000,'purchase_price'=>12000,'threshold'=>50],
        ];

        $products = collect($productsData)->map(function($d) use ($categories){
            $cat = $categories->firstWhere('name',$d['category']);
            return Product::create([
                'code'=>$d['code'],
                'name'=>$d['name'],
                'generic_name'=>$d['generic_name'],
                'laboratory'=>$d['laboratory'],
                'category_id'=>$cat?->id,
                'base_unit'=>'boite',
                'sale_price'=>$d['sale_price'],
                'purchase_price'=>$d['purchase_price'],
                'alert_threshold'=>$d['threshold'],
                'is_active'=>true,
            ]);
        });

        foreach ($pharmacies as $idx=>$ph) {
            foreach ($products as $p) {
                $qty = match($p->code) {
                    'AMOX500' => [8,40,20][$idx],
                    'INSNPH' => [5,12,8][$idx],
                    'IBU400' => [0,15,9][$idx],
                    'ARTEINJ' => [14,25,10][$idx],
                    'CAPTO25' => [19,30,12][$idx],
                    'PARA1G' => [12,35,18][$idx],
                    default => [142,90,60][$idx],
                };
                StockLevel::create(['pharmacy_id'=>$ph->id,'product_id'=>$p->id,'quantity'=>$qty]);
            }
        }

        $insurers = collect([
            ['name'=>'NSIA Assurances','code'=>'NSIA','is_active'=>true],
            ['name'=>'UGAR Activa','code'=>'UGAR','is_active'=>true],
            ['name'=>'Saham / Sanlam','code'=>'SAHAM','is_active'=>true],
            ['name'=>'CNSS Guinée','code'=>'CNSS','is_active'=>true],
        ])->map(fn($i)=>InsuranceCompany::create($i));

        $customers = collect([
            ['name'=>'Mamadou Camara','phone'=>'622000001','insurance_company_id'=>$insurers[0]->id],
            ['name'=>'Fatoumata Bah','phone'=>'622000002','insurance_company_id'=>$insurers[1]->id],
            ['name'=>'Ibrahima Sylla','phone'=>'622000003','insurance_company_id'=>$insurers[0]->id],
            ['name'=>'Aïssatou Barry','phone'=>'622000004','insurance_company_id'=>$insurers[3]->id],
        ])->map(fn($c)=>Customer::create($c));

        $ph0 = $pharmacies[0];
        $salesData = [
            ['#TK-90412',4,'186500','Payé',$insurers[0]->id,$customers[0]->id],
            ['#TK-90411',2,'45000','Payé',null,null],
            ['#TK-90410',6,'312000','En attente',$insurers[1]->id,$customers[1]->id],
            ['#TK-90409',1,'28500','Payé',null,null],
        ];
        foreach ($salesData as [$num,$items,$total,$status,$ins,$cust]) {
            $sale = Sale::create([
                'sale_number'=>$num,
                'pharmacy_id'=>$ph0->id,
                'user_id'=>$user->id,
                'customer_id'=>$cust,
                'insurance_company_id'=>$ins,
                'subtotal'=>(int)$total,
                'total'=>(int)$total,
                'client_part'=>$ins? (int)round($total*0.2) : (int)$total,
                'insurer_part'=>$ins? (int)round($total*0.8) : 0,
                'status'=>$status==='Payé'?'completed':'pending',
            ]);
            $prod = $products->first();
            SaleItem::create(['sale_id'=>$sale->id,'product_id'=>$prod->id,'quantity'=>1,'unit'=>'boite','unit_price'=>$prod->sale_price,'total'=>$prod->sale_price]);
            Payment::create(['sale_id'=>$sale->id,'method'=>$ins?'assurance':'especes','amount'=>$sale->client_part]);
            if ($ins) {
                InsuranceClaim::create([
                    'sale_id'=>$sale->id,
                    'insurance_company_id'=>$ins,
                    'total_amount'=>$sale->total,
                    'client_part'=>$sale->client_part,
                    'insurer_part'=>$sale->insurer_part,
                    'status'=>'a_facturer',
                ]);
            }
        }

        $tr = Transfer::create([
            'reference'=>'TRF-231',
            'from_pharmacy_id'=>$pharmacies[0]->id,
            'to_pharmacy_id'=>$pharmacies[1]->id,
            'status'=>'expedie',
            'requested_by'=>$user->id,
            'shipped_at'=>now(),
        ]);
        TransferItem::create(['transfer_id'=>$tr->id,'product_id'=>$products->firstWhere('code','AMOX500')->id,'quantity_requested'=>20,'quantity_shipped'=>20]);
    }
}

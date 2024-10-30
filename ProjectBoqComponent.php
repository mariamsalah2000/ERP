<?php

namespace Modules\Sales\App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Livewire\WithoutUrlPagination;
use Modules\Management\App\Models\Area;
use Modules\Management\App\Models\Room;
use Modules\Management\App\Models\Type;
use Modules\Management\App\Models\Level;
use Modules\Management\App\Models\Product;
use Modules\Management\App\Models\Project;
use Modules\Management\App\Models\Category;
use Modules\Management\App\Models\Appartment;
use Modules\Management\App\Models\RoomProduct;
use Modules\Management\App\Actions\CustomTCPDF;

class ProjectBoqComponent extends Component
{
    use WithPagination, WithoutUrlPagination;

    public $categories,$notes = [];
    public $level_id,$area_id,$appartment_id,$room_id,$note,$type_id,$search,$category_id;
    public $project;
    public $data = [];

    public function mount($project = null)
    {
        $this->project  = $project;

        $this->categories       = Category::orderBy('name')->get(['id', 'name']);

        $project            = Project::with('areas.types.levels.appartments.rooms')->find($this->project->id);
    }

    public function getProductsProperty()
    {
        return Product::with([
            'room_products'=> fn($q) => $q->whereProjectId($this->project->id)
                ->when($this->category_id,fn($q)    => $q->whereHas('product',fn($x) => $x->whereCategoryId($this->category_id)))
                ->when($this->area_id,fn($q)        => $q->whereHas('area',fn($q) => $q->whereId($this->area_id)))
                ->when($this->type_id,fn($q)        => $q->whereHas('type',fn($q) => $q->whereId($this->type_id)))
                ->when($this->level_id,fn($q)       => $q->whereHas('level',fn($q) => $q->whereId($this->level_id)))
                ->when($this->appartment_id,fn($q)  => $q->whereHas('appartment',fn($q) => $q->whereId($this->appartment_id)))
                ->when($this->room_id,fn($q)        => $q->whereHas('room',fn($q) => $q->whereId($this->room_id)))
                ->when($this->search,fn($q)         => $q->whereHas('product',fn($x) => $x->where('name','LIKE','%'.$this->search.'%')->orWhere('code','LIKE','%'.$this->search.'%'))),
            'unit'
        ])
        ->whereHas(
            'room_products',fn($q) => $q->whereProjectId($this->project->id)
                ->when($this->category_id,fn($q)    => $q->whereHas('product',fn($x) => $x->whereCategoryId($this->category_id)))
                ->when($this->area_id,fn($q)        => $q->whereHas('area',fn($q) => $q->whereId($this->area_id)))
                ->when($this->type_id,fn($q)        => $q->whereHas('type',fn($q) => $q->whereId($this->type_id)))
                ->when($this->level_id,fn($q)       => $q->whereHas('level',fn($q) => $q->whereId($this->level_id)))
                ->when($this->appartment_id,fn($q)  => $q->whereHas('appartment',fn($q) => $q->whereId($this->appartment_id)))
                ->when($this->room_id,fn($q)        => $q->whereHas('room',fn($q) => $q->whereId($this->room_id)))
                ->when($this->search,fn($q)         => $q->whereHas('product',fn($x) => $x->where('name','LIKE','%'.$this->search.'%')->orWhere('code','LIKE','%'.$this->search.'%')))
        )
        ->get()
        ->map(function($product)
        {
            return [
                'id'                    => $product->id,
                'name'                  => $product->name,
                'code'                  => $product->code,
                'specification'         => $product->specification,
                'image'                 => $product?->image,
                'unit_name'             => $product->unit->name ?? '-',
                'category_name'         => $product->category->name ?? '-',
                'total_quantity'        => $product->room_products->sum('full_quantity')
            ];
        });
    }

    public function getAreasProperty()
    {
        return $this->project->areas;
    }

    public function getTypesProperty()
    {
        return Type::whereAreaId($this->area_id)->orderBy('name')->get(['id', 'name']);
    }

    public function getLevelsProperty()
    {
        return Level::whereTypeId($this->type_id)->orderBy('name')->get(['id', 'name']);
    }

    public function getAppartmentsProperty()
    {
        return Appartment::whereLevelId($this->level_id)->orderBy('name')->get(['id', 'name']);
    }

    public function getRoomsProperty()
    {
        if($this->level_id || $this->appartment_id)
        {
            return Room::whereLevelId($this->level_id)
                            ->orWhere('appartment_id', $this->appartment_id)
                            ->get(['id', 'name']);
        }

        return [];
    }


    public function updated($propertyName)
    {
        switch ($propertyName) {
            case 'area_id':
                $this->type_id = '';
                $this->level_id = '';
                $this->appartment_id = '';
                $this->room_id = '';
                break;
            case 'type_id':
                $this->level_id = '';
                $this->appartment_id = '';
                $this->room_id = '';
                break;
            case 'level_id':
                $this->appartment_id = '';
                $this->room_id = '';
                break;
            case 'appartment_id':
                $this->room_id = '';
                break;

            default:
                # code...
                break;
        }

    }

    // public function exportPdf()
    // {
    //     $details = [];

    //     foreach($this->products as $key => $product)
    //     {
    //         $details[] = [
    //             'id'                => $product['id'],
    //             'code'              => $product['code'] ?? '-',
    //             'name'              => $product['name'] ?? '-',
    //             'specification'     => $product['specification'] ?? "-",
    //             'image'             => $product['image'] ? $product['image']->getUrl() : NULL,
    //             'unit_name'         => $product['unit_name'] ?? "-",
    //             'total_quantity'    => $product['total_quantity'],
    //             'single_price'      => 0,
    //             'total_price'       => 0,
    //             'notes'             => $this->notes[$key] ?? NULL
    //         ];
    //     }

    //     // Generate PDF
    //     $pdf = PDF::loadView('sales::project_boq.pdf', ['data' => $details]);

    //     return response()->streamDownload(function () use ($pdf) {
    //         echo $pdf->output();
    //     }, 'BOQ '.date('Y-m-d H:i:s').'.pdf');
    // }
    public function exportPdf(CustomTCPDF $pdf)
    {
        $details = [];

        foreach($this->products as $key => $product)
        {
            $details[] = [
                'id'                => $product['id'],
                'code'              => $product['code'] ?? '-',
                'name'              => $product['name'] ?? '-',
                'specification'     => $product['specification'] ?? "-",
                'image'             => $product['image'] ? $product['image']->getUrl() : NULL,
                'unit_name'         => $product['unit_name'] ?? "-",
                'total_quantity'    => $product['total_quantity'],
                'single_price'      => 0,
                'total_price'       => 0,
                'notes'             => $this->notes[$key] ?? NULL
            ];
        }

        // Generate PDF
        $filename = "export-BOQ.pdf";

        $pdf->SetTitle("Export-BOQ");
        $pdf->SetSubject("Export-BOQ");
        $pdf->SetKeywords('Export, PDF, Management');

        // set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 25);
        $pdf->AddPage('L', 'A4');

        $pdf->writeHTML(view("sales::project_boq.pdf", ['data' => $details])->render());

        $pdf->lastPage();

        return response()->streamDownload(function () use ($pdf,$filename) {
                    $pdf->Output($filename, 'I');
                }, 'BOQ '.date('Y-m-d H:i:s').'.pdf',);

        // return true;
    }

    public function render()
    {
        return view('sales::livewire.project_boq', [
            'products'      => $this->products,
            'areas'         => $this->project->areas,
            'types'         => $this->types,
            'levels'        => $this->levels,
            'appartments'   => $this->appartments,
            'rooms'         => $this->rooms,
        ]);
    }

}

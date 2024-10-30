<?php

namespace Modules\Sales\App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Sales\App\Models\BoqDetail;
use Modules\Sales\App\Models\ProjectBoq;
use Modules\Management\App\Models\Product;
use Modules\Management\App\Models\RoomProduct;
use Modules\Management\App\Models\Project;
use Modules\Sales\App\Services\ProjectBoqService;
use Modules\Sales\App\Traits\MediaUploadingTrait;
use Modules\Sales\App\Http\Requests\ProjectBoqRequest;

class ProjectBoqController extends Controller
{
    use MediaUploadingTrait;
    /**
     * The view to render
     *
     * @var string
    */
    protected $view = 'sales::project_boq';

    public function __construct(private ProjectBoqService $projectBoqService)
    {
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
    */
    public function index()
    {
        $boqs = $this->projectBoqService->index();

        return view($this->view . '.index', compact('boqs'));
    }

    public function create(Project $project)
    {
        $products = $project->products;
        return view($this->view . '.form', compact('project','products'));
    }

    public function projectIndex(Project $project)
    {
        $boqs = ProjectBoq::where('project_id',$project->id)->latest()->get();
        $products = [];
        return view($this->view.'.project-index',compact('boqs','project'));
    }

    public function store(ProjectBoqRequest $request)
    {
        try
        {
            DB::beginTransaction();

            $boq = ProjectBoq::create([
                'project_id'        => $request['project_id'],
                'created_by_id'     => auth()->id(),
            ]);

            foreach($request['product_id'] as $key => $product_id)
            {
                $product = Product::findOrFail($product_id);

                if($product)
                {
                    $detail = BoqDetail::create([
                        'boq_id'            => $boq->id,
                        'product_id'        => $product_id,
                        'note'              => $request['notes'][$key] ?? NULL,
                        'quantity'          => $request['quantity'][$key],
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => __('dashboard.created_successfully'), 'status' => 'success']);
        }
        catch (\Exception $e)
        {
            DB::rollBack();

            dd($e->getMessage());
            return response()->json(['message' => $e->getMessage(), 'status' => 'error']);
        }
    }

    public function show(ProjectBoq $boq)
    {
       $boq->load([
            'project',
            'details.product'
        ]);

        return view($this->view.'.show',compact('boq'));
    }
    public function updateStatus($boq , $status)
    {
        $boq = ProjectBoq::findOrFail($boq);
        try{
            DB::beginTransaction();

            $boq->update([
                'status'        => $status
            ]);

            DB::commit();

            return redirect()->back()->with('success','Status Updated Successfully');
        }
        catch (\Exception $e)
        {
            DB::rollBack();
            return redirect()->back()->with('error',$e->getMessage());
        }
    }

    public function destroy(ProjectBoq $projectBoq) : RedirectResponse
    {
        $this->projectBoqService->delete($projectBoq);

        return redirect()->back()->with('success',__('dashboard.deleted_successfully'));
    }

    public function export($boq)
    {
        $boqq = ProjectBoq::findOrFail($boq);
        $details = [];
        foreach($boqq->details as $key=>$bo)
        {
            $details[] = [
                'product_code'      => optional($bo->product)->full_code ?? '-',
                'product_name'      => optional($bo->product->product)->name ?? '-',
                'description'       => optional($bo->product->product)->specification ?? "-",
                'image'             => $bo->product->product->image?->getUrl() ,
                'unit'              =>optional($bo->product->product->product_feature->unit)->name ?? "-",
                'needed_quantity'   => $bo->quantity,
                'single_price'      => "0 LE.",
                'total_price'       => "0 LE.",
                'note'              => $bo->note
            ];
        }

        $data = [
            'title' => $boqq->project->name.' Bill Of Quantity',
            'tableData' => $details,
        ];

        $pdf = Pdf::loadView('sales::project_boq.pdf', $data);

        return $pdf->download('Boq Confirmed.pdf');
    }

}

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class RedirectController extends Controller
{
    public function redirectAction()
    {
        $uri = $this->generateUrl($_redirectTo);

        $response = new RedirectResponse($uri, 301);
        $response->setContent($this->render(
            'templates/301.html.twig',
            array( 'uri' => $uri )
        ));

        return $response;
    }
}
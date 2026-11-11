import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { HelpCircle } from 'lucide-react';

export function RulesHelpDialog() {
    const { __ } = useLocalizer();
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button type="button" variant="ghost" size="sm" className="h-8 w-8 p-0">
                    <HelpCircle className="h-4 w-4" />
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[95vh] max-w-[95vw] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{__('Extra Option (JSON DATA) Documentation')}</DialogTitle>
                    <DialogDescription>{__('Learn how to configure different field types using JSON rules')}</DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Text Configuration */}
                    <div className="space-y-2">
                        <h3 className="font-semibold">{__('For Text-box Configuration:')}</h3>
                        <hr />
                        <p className="text-sm text-muted-foreground">
                            {__('You can set the minimum charset value and maximum charset value using the json.')}
                        </p>
                        <pre className="rounded-md bg-muted p-4 text-sm">
                            <code>
                                {`{
  "min": "0",
  "max": "255"
}`}
                            </code>
                        </pre>
                    </div>

                    {/* Number Configuration */}
                    <div className="space-y-2">
                        <h3 className="font-semibold">{__('For Number Configuration:')}</h3>
                        <hr />
                        <p className="text-sm text-muted-foreground">{__('You can set the minimum number and maximum number using the json.')}</p>
                        <pre className="rounded-md bg-muted p-4 text-sm">
                            <code>
                                {`{
  "min": "0",
  "max": "255"
}`}
                            </code>
                        </pre>
                    </div>

                    {/* Text-area Configuration */}
                    <div className="space-y-2">
                        <h3 className="font-semibold">{__('For Text-area Configuration:')}</h3>
                        <hr />
                        <p className="text-sm text-muted-foreground">
                            {__('You can set the minimum charset value and maximum charset value using the json.')}
                        </p>
                        <pre className="rounded-md bg-muted p-4 text-sm">
                            <code>
                                {`{
  "minlength": "0",
  "maxlength": "255"
}`}
                            </code>
                        </pre>
                    </div>

                    {/* Code Editor Configuration */}
                    <div className="space-y-2">
                        <h3 className="font-semibold">{__('For Code Editor Configuration:')}</h3>
                        <hr />
                        <p className="text-sm text-muted-foreground">
                            {__(
                                'You can use HTML, CSS, PHP, JavaScript, Java, C#, C, C++, Clojure, Go, Groovy, JSON, Scala, Ruby, XML, and others as languages.',
                            )}
                        </p>
                        <pre className="rounded-md bg-muted p-4 text-sm">
                            <code>
                                {`{
  "theme": "dark",
  "language": "html"
}`}
                            </code>
                        </pre>
                    </div>

                    {/* Checkbox Configuration */}
                    <div className="space-y-2">
                        <h3 className="font-semibold">{__('For Checkbox Configuration:')}</h3>
                        <hr />
                        <p className="text-sm text-muted-foreground">{__('You can use Checkbox label and value using the json.')}</p>
                        <pre className="rounded-md bg-muted p-4 text-sm">
                            <code>
                                {`{
  "label": "key/value",
  "label2": "key2/value2"
}`}
                            </code>
                        </pre>
                    </div>

                    {/* Radio Configuration */}
                    <div className="space-y-2">
                        <h3 className="font-semibold">{__('For Radio Configuration:')}</h3>
                        <hr />
                        <p className="text-sm text-muted-foreground">{__('You can use radio label and value using the json.')}</p>
                        <pre className="rounded-md bg-muted p-4 text-sm">
                            <code>
                                {`{
  "label": "key/value",
  "label2": "key2/value2"
}`}
                            </code>
                        </pre>
                    </div>

                    {/* Select Dropdown Configuration */}
                    <div className="space-y-2">
                        <h3 className="font-semibold">{__('For Select Dropdown Configuration:')}</h3>
                        <hr />
                        <p className="text-sm text-muted-foreground">
                            {__(
                                'You can use Select Dropdown options using the json with an "options" key. The key is the label (displayed text) and the value is the actual value.',
                            )}
                        </p>
                        <pre className="rounded-md bg-muted p-4 text-sm">
                            <code>
                                {`{
  "options": {
    "10 items": "10",
    "25 items": "25",
    "50 items": "50",
    "100 items": "100"
  }
}`}
                            </code>
                        </pre>
                    </div>

                    {/* File Configuration */}
                    <div className="space-y-2">
                        <h3 className="font-semibold">{__('For File Configuration:')}</h3>
                        <hr />
                        <p className="text-sm text-muted-foreground">{__('You can use File accept using the json.')}</p>
                        <pre className="rounded-md bg-muted p-4 text-sm">
                            <code>
                                {`{
  "accept": "application/msword,application/pdf"
}`}
                            </code>
                        </pre>
                    </div>

                    {/* Image Configuration */}
                    <div className="space-y-2">
                        <h3 className="font-semibold">{__('For Image Configuration:')}</h3>
                        <hr />
                        <p className="text-sm text-muted-foreground">{__('You can use image accept using the json.')}</p>
                        <pre className="rounded-md bg-muted p-4 text-sm">
                            <code>
                                {`{
  "accept": "image/*"
}`}
                            </code>
                        </pre>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
